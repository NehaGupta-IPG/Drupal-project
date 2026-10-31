<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_tree_ui\MenuLinkMovability;
use Drupal\menu_tree_ui\MenuTreeRowContext;
use Drupal\menu_tree_ui\MenuTreeUiConstants;
use Drupal\treegrid\TreegridColumnManager;
use Drupal\treegrid\TreegridCsrfToken;
use Drupal\treegrid\TreegridRows;
use Drupal\menu_tree_ui\Service\MenuTreeLoader;
use Drupal\menu_tree_ui\Service\MenuTreeMover;
use Drupal\menu_tree_ui\Service\MenuTreeWorkingCopyLock;
use Drupal\treegrid_reorder\TreegridWorkingCopy;
use Drupal\system\MenuInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Routes for the lazy-loaded menu tree UI.
 *
 * Five actions:
 *   - page():         full admin page, server-rendered top level + JS shell.
 *   - children():     rows for one level (called on expand and for paging).
 *   - search():       search results, swapped in by htmx.
 *   - move():         drag-and-drop / keyboard reorder.
 *   - columnUpdate(): interactive column toggle (e.g. "enabled").
 *
 * @internal
 */
final class MenuTreeController extends ControllerBase {

  public function __construct(
    private readonly MenuTreeLoader $loader,
    private readonly MenuTreeMover $mover,
    private readonly TreegridColumnManager $columnManager,
    private readonly TreegridWorkingCopy $workingCopy,
    private readonly MenuLinkManagerInterface $menuLinkManager,
    private readonly MenuTreeWorkingCopyLock $workingCopyLock,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('menu_tree_ui.loader'),
      $container->get('menu_tree_ui.mover'),
      $container->get('plugin.manager.treegrid_column'),
      $container->get('menu_tree_ui.working_copy'),
      $container->get('plugin.manager.menu.link'),
      $container->get('menu_tree_ui.working_copy_lock'),
    );
  }

  /**
   * Admin page for a single menu — server-renders the top level.
   */
  public function page(MenuInterface $menu): array {
    // Render only the first page on the server; the client fetches the rest as
    // the editor scrolls. Slicing before the entity load and cell build bounds
    // the cost that scales with width (see MenuTreeUiConstants).
    $slice = $this->loader->loadLevelForEditing($menu->id(), NULL, 0, MenuTreeUiConstants::TOP_LEVEL_PAGE_SIZE);
    $top_level = $slice->elements;
    $top_level_total = $slice->total;
    $columns = $this->columnManager->getSortedColumns();
    $entity_map = $this->loadContentEntitiesForElements($top_level);
    $reorder_blocked = $this->hasPendingRevisions($menu->id());

    $column_headers = [];
    foreach ($columns as $col_id => $column) {
      $column_headers[$col_id] = [
        'id' => $col_id,
        'label' => $column->getHeader(),
      ];
    }

    $rendered_rows = $this->buildRows($top_level, $entity_map, $columns, '', 0, $top_level_total, $reorder_blocked);

    $menu_name = $menu->id();
    $has_changes = $this->workingCopy->hasChanges($menu_name);
    $locked = $this->workingCopy->isLocked($menu_name);
    $lock_owner = '';
    $lock_updated = 0;
    $break_lock_url = '';
    if ($locked) {
      $lock_owner = $this->lockOwnerName($menu_name);
      $lock_updated = $this->workingCopy->getLockMetadata($menu_name)?->getUpdated() ?? 0;
      $break_lock_url = Url::fromRoute('menu_tree_ui.break_lock', ['menu' => $menu_name])->toString();
    }

    $build = [
      '#theme' => 'menu_tree_ui_page',
      '#menu' => $menu,
      '#top_level' => $rendered_rows,
      '#columns' => $column_headers,
      '#total' => $top_level_total,
      '#page_size' => MenuTreeUiConstants::TOP_LEVEL_PAGE_SIZE,
      '#truncated' => $top_level_total > MenuTreeUiConstants::TOP_LEVEL_PAGE_SIZE,
      '#has_changes' => $has_changes,
      '#reorder_blocked' => $reorder_blocked,
      '#locked' => $locked,
      '#lock_owner' => $lock_owner,
      '#lock_updated' => $lock_updated,
      '#break_lock_url' => $break_lock_url,
      '#add_link_url' => Url::fromRoute('entity.menu.add_link_form', ['menu' => $menu_name], [
        'query' => [
          'destination' => Url::fromRoute('menu_tree_ui.edit', ['menu' => $menu_name])->toString(),
        ],
      ])->toString(),
      '#search_action' => Url::fromRoute('menu_tree_ui.search', ['menu' => $menu_name])->toString(),
      '#children_action' => Url::fromRoute('menu_tree_ui.children', ['menu' => $menu_name])->toString(),
      '#column_update_url' => Url::fromRoute('menu_tree_ui.column_update', ['menu' => $menu_name])->toString(),
      '#move_url' => Url::fromRoute('menu_tree_ui.move', ['menu' => $menu_name])->toString(),
      '#attached' => [
        'library' => ['menu_tree_ui/menu_tree_ui'],
        'drupalSettings' => [
          'menuTreeUi' => [
            'saveUrl' => Url::fromRoute('menu_tree_ui.save', ['menu' => $menu_name])->toString(),
            'cancelUrl' => Url::fromRoute('menu_tree_ui.cancel', ['menu' => $menu_name])->toString(),
          ],
        ],
      ],
      // The rendered tree reflects the per-user working copy in tempstore,
      // which page cache metadata cannot express, so the page is not cached.
      '#cache' => [
        'max-age' => 0,
      ],
    ];
    $build['#csrf_token'] = TreegridCsrfToken::addPlaceholder($build);
    return $build;
  }

  /**
   * Title callback for the tree UI page.
   */
  public function pageTitle(MenuInterface $menu): TranslatableMarkup {
    return $this->t('Edit @label', ['@label' => $menu->label()]);
  }

  /**
   * Returns one level of children as a rows response.
   *
   * Query params:
   *   - parent: plugin ID of the parent link, or empty for top level.
   *   - offset: the first row of a top-level page.
   */
  public function children(MenuInterface $menu, Request $request): array {
    $parent_id = $request->query->get('parent', '') ?: NULL;
    if ($parent_id !== NULL) {
      $parent_definition = $this->menuLinkManager->getDefinition($parent_id, FALSE);
      if (!$parent_definition || $parent_definition['menu_name'] !== $menu->id()) {
        // Access is checked for the route's menu only; without this, a
        // parent id from another menu would return that menu's children.
        // getDefinition() returns FALSE (not NULL) for an unknown id.
        throw new NotFoundHttpException();
      }
    }
    $offset = max(0, (int) $request->query->get('offset', 0));
    // Only the top level is paged; deeper levels return all their children so
    // the expand path, which does not page, is unaffected.
    $slice = $parent_id === NULL
      ? $this->loader->loadLevelForEditing($menu->id(), NULL, $offset, MenuTreeUiConstants::TOP_LEVEL_PAGE_SIZE)
      : $this->loader->loadLevelForEditing($menu->id(), $parent_id);
    $columns = $this->columnManager->getSortedColumns();
    $entity_map = $this->loadContentEntitiesForElements($slice->elements);
    $reorder_blocked = $this->hasPendingRevisions($menu->id());

    $rows = $this->buildRows($slice->elements, $entity_map, $columns, $parent_id ?? '', $offset, $slice->total, $reorder_blocked);
    return TreegridRows::build($rows, $slice->total, $slice->nextId);
  }

  /**
   * Returns search results for htmx to swap in.
   *
   * The X-Menu-Tree-Ui-Search header marks the response as search results, so
   * the client can refuse anything else (an error page, a login redirect).
   */
  public function search(MenuInterface $menu, Request $request): array {
    $query = (string) $request->query->get('q', '');

    return [
      '#theme' => 'menu_tree_ui_search_results',
      '#results' => $this->loader->search($menu->id(), $query),
      '#query' => $query,
      '#cache' => ['max-age' => 0],
      '#attached' => [
        'http_header' => [['X-Menu-Tree-Ui-Search', '1']],
      ],
    ];
  }

  /**
   * Records a drag-and-drop move in the working copy and returns JSON.
   *
   * Body shape: { id, new_parent, before_id }.
   */
  public function move(MenuInterface $menu, Request $request): JsonResponse {
    $payload = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse([
        'ok' => FALSE,
        'code' => 'bad_request',
        'message' => (string) $this->t('JSON body required.'),
      ], 400);
    }

    $id = (string) ($payload['id'] ?? '');
    if ($id === '') {
      return new JsonResponse(['ok' => FALSE, 'code' => 'bad_request', 'message' => (string) $this->t('Missing id.')], 400);
    }
    $new_parent = (string) ($payload['new_parent'] ?? '');
    $before_id = $payload['before_id'] ?? NULL;
    if ($before_id !== NULL) {
      $before_id = (string) $before_id;
      if ($before_id === '') {
        $before_id = NULL;
      }
    }

    $menu_name = $menu->id();
    return $this->withMenuLock($menu_name, function () use ($menu_name, $id, $new_parent, $before_id): JsonResponse {
      if ($this->workingCopy->isLocked($menu_name)) {
        return new JsonResponse(
          ['ok' => FALSE, 'code' => 'locked', 'message' => $this->lockedMessage($menu_name)],
          409,
        );
      }

      if ($this->hasPendingRevisions($menu_name)) {
        return $this->pendingRevisionsResponse();
      }

      // Validate against the live tree plus the pending moves, then record the
      // move in the working copy instead of writing it. Save validates the
      // recorded moves in order before it writes any of them.
      $validation = $this->mover->validateMove($menu_name, $id, $new_parent, $before_id);
      if (!$validation['ok']) {
        return new JsonResponse($validation, 400);
      }

      // addOp is the authoritative lock gate: it refuses if the working copy
      // was claimed by another editor between the check above and this write.
      // Honor that refusal rather than reporting a success that did not happen.
      if (!$this->workingCopy->addOp($menu_name, [
        'type' => 'move',
        'id' => $id,
        'new_parent' => $new_parent,
        'before_id' => $before_id,
      ])) {
        return new JsonResponse(
          ['ok' => FALSE, 'code' => 'locked', 'message' => $this->lockedMessage($menu_name)],
          409,
        );
      }

      return new JsonResponse(['ok' => TRUE, 'dirty' => TRUE]);
    });
  }

  /**
   * Commits the menu's pending working-copy moves to live storage.
   *
   * Nothing is written when a recorded move no longer validates. A partial
   * failure happens only when a module hook or a concurrent change makes a
   * later move fail while the batch is being written; the already-applied
   * moves are then dropped from the working copy so a retry does not replay
   * them, and the response names how many of the batch went through.
   */
  public function save(MenuInterface $menu): JsonResponse {
    $menu_name = $menu->id();
    return $this->withMenuLock($menu_name, function () use ($menu_name): JsonResponse {
      if ($this->workingCopy->isLocked($menu_name)) {
        return new JsonResponse(
          ['ok' => FALSE, 'code' => 'locked', 'message' => $this->lockedMessage($menu_name)],
          409,
        );
      }

      if ($this->hasPendingRevisions($menu_name)) {
        return $this->pendingRevisionsResponse();
      }

      $ops = $this->workingCopy->getOps($menu_name);
      $result = $this->mover->commitWorkingCopy($menu_name, $ops);
      if (!empty($result['ok'])) {
        $this->workingCopy->clear($menu_name);
        $result['dirty'] = $this->workingCopy->hasChanges($menu_name);
        return new JsonResponse($result);
      }

      $applied = $result['applied_ops'] ?? [];
      if ($applied !== []) {
        $this->workingCopy->dropOps($menu_name, $applied);
        $total = count(array_filter($ops, static fn(array $op): bool => ($op['type'] ?? '') === 'move'));
        $result['message'] = (string) $this->t('Saved @applied of @total changes; the rest could not be saved. @reason', [
          '@applied' => count($applied),
          '@total' => $total,
          '@reason' => $result['message'] ?? '',
        ]);
      }
      return new JsonResponse($result, 400);
    });
  }

  /**
   * Discards the menu's pending working-copy moves.
   */
  public function cancel(MenuInterface $menu): JsonResponse {
    $menu_name = $menu->id();
    return $this->withMenuLock($menu_name, function () use ($menu_name): JsonResponse {
      if ($this->workingCopy->isLocked($menu_name)) {
        return new JsonResponse(
          ['ok' => FALSE, 'code' => 'locked', 'message' => $this->lockedMessage($menu_name)],
          409,
        );
      }
      $this->workingCopy->clear($menu_name);
      return new JsonResponse(['ok' => TRUE, 'dirty' => $this->workingCopy->hasChanges($menu_name)]);
    });
  }

  /**
   * Applies an interactive column update and returns the outcome as JSON.
   *
   * Body shape: { column_id, row_id, value }. The column and row IDs must be
   * non-empty strings and the value a boolean.
   */
  public function columnUpdate(MenuInterface $menu, Request $request): JsonResponse {
    $payload = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse([
        'ok' => FALSE,
        'code' => 'bad_request',
        'message' => (string) $this->t('JSON body required.'),
      ], 400);
    }

    $column_id = $payload['column_id'] ?? NULL;
    $row_id = $payload['row_id'] ?? NULL;
    $value = $payload['value'] ?? NULL;

    if (!is_string($column_id) || $column_id === '' || !is_string($row_id) || $row_id === '' || !is_bool($value)) {
      return new JsonResponse([
        'ok' => FALSE,
        'code' => 'bad_request',
        'message' => (string) $this->t('Missing or invalid column_id, row_id or value.'),
      ], 400);
    }
    if (!$this->columnManager->hasDefinition($column_id)) {
      return new JsonResponse([
        'ok' => FALSE,
        'code' => 'unknown_column',
        'message' => (string) $this->t('Unknown column.'),
      ], 400);
    }

    $link_definition = $this->menuLinkManager->getDefinition($row_id, FALSE);
    if (!$link_definition || $link_definition['menu_name'] !== $menu->id()) {
      // Access is checked for the route's menu only; without this, a link ID
      // from another menu would let a column update reach it.
      return new JsonResponse(['ok' => FALSE, 'code' => 'not_found', 'message' => (string) $this->t('Link not found.')], 404);
    }

    $column = $this->columnManager->createInstance($column_id);
    return $this->withMenuLock($menu->id(), function () use ($column, $row_id, $value): JsonResponse {
      if (!$column->handleUpdate($row_id, $value)) {
        return new JsonResponse([
          'ok' => FALSE,
          'code' => 'not_updatable',
          'message' => (string) $this->t('This value cannot be updated.'),
        ], 400);
      }
      return new JsonResponse(['ok' => TRUE]);
    });
  }

  // Private helpers.

  /**
   * Runs an action while holding the menu's working-copy lock.
   *
   * Serializes the actions that read or write a menu's working copy, and the
   * column updates that write its live links. Returns a 409 'busy' response
   * without running the action when the lock cannot be acquired.
   *
   * @param string $menu_name
   *   The menu whose links or working copy the action touches.
   * @param callable(): \Symfony\Component\HttpFoundation\JsonResponse $action
   *   The action to run.
   */
  private function withMenuLock(string $menu_name, callable $action): JsonResponse {
    if (!$this->workingCopyLock->acquire($menu_name)) {
      return new JsonResponse([
        'ok' => FALSE,
        'code' => 'busy',
        'message' => (string) $this->t('Another change to this menu is being processed. Try again.'),
      ], 409);
    }
    try {
      return $action();
    }
    finally {
      $this->workingCopyLock->release($menu_name);
    }
  }

  /**
   * Whether any content link in the menu has a pending (workspace) revision.
   *
   * Writing such a link through the menu link manager makes a new default
   * revision that publishing the workspace later overwrites.
   */
  private function hasPendingRevisions(string $menu_name): bool {
    /** @var \Drupal\menu_link_content\MenuLinkContentStorageInterface $storage */
    $storage = $this->entityTypeManager()->getStorage('menu_link_content');
    $pending_ids = $storage->getMenuLinkIdsWithPendingRevisions();
    if ($pending_ids === []) {
      return FALSE;
    }
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('menu_name', $menu_name)
      ->condition('id', $pending_ids, 'IN')
      ->range(0, 1)
      ->execute();
    return $ids !== [];
  }

  /**
   * Builds the 409 response for a reorder refused over pending revisions.
   */
  private function pendingRevisionsResponse(): JsonResponse {
    return new JsonResponse([
      'ok' => FALSE,
      'code' => 'pending_revisions',
      'message' => (string) $this->t('Links in this menu have pending revisions, so it cannot be reordered.'),
    ], 409);
  }

  /**
   * Whether a link's title should render as a link to its destination.
   *
   * Mirrors the classic menu admin, which links each title to the menu link's
   * URL. Route-less destinations (<nolink> and the like) have nowhere to go, so
   * their title renders as plain text.
   *
   * @param \Drupal\Core\Menu\MenuLinkInterface $link
   *   The menu link.
   *
   * @return bool
   *   TRUE if the title should be a link.
   */
  private function isLinked(MenuLinkInterface $link): bool {
    $url = $link->getUrlObject();
    if (!$url->isRouted()) {
      return TRUE;
    }
    return !in_array($url->getRouteName(), ['<nolink>', '<none>', '<button>'], TRUE);
  }

  /**
   * The display name of the user holding the menu's working copy.
   *
   * @param string $menu_name
   *   The menu entity ID.
   *
   * @return string
   *   The owner's display name, or their user ID if the account is gone.
   */
  private function lockOwnerName(string $menu_name): string {
    $lock = $this->workingCopy->getLockMetadata($menu_name);
    if ($lock === NULL) {
      return '';
    }
    $owner = $this->entityTypeManager()->getStorage('user')->load($lock->getOwnerId());
    return $owner ? $owner->getDisplayName() : (string) $lock->getOwnerId();
  }

  /**
   * The "another user is editing" message for a locked working copy.
   *
   * @param string $menu_name
   *   The menu entity ID.
   *
   * @return string
   *   The user-facing lock message.
   */
  private function lockedMessage(string $menu_name): string {
    return (string) $this->t('@user is currently editing this menu. Your changes cannot be saved until they finish.', [
      '@user' => $this->lockOwnerName($menu_name),
    ]);
  }

  /**
   * Builds the row render arrays for a slice of one level.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $elements
   *   The slice's tree elements, in order.
   * @param array<string, \Drupal\menu_link_content\MenuLinkContentInterface> $entity_map
   *   Pre-loaded entities keyed by plugin ID.
   * @param \Drupal\treegrid\Plugin\TreegridColumn\TreegridColumnInterface[] $columns
   *   The active column plugins keyed by plugin ID.
   * @param string $parent_id
   *   The parent link plugin ID, or '' for the top level.
   * @param int $offset
   *   The number of rows of the level before the slice.
   * @param int $set_size
   *   The number of rows in the whole level.
   * @param bool $reorder_blocked
   *   Whether reordering is off for the whole menu.
   *
   * @return array
   *   One menu_tree_ui_row render array per element.
   */
  private function buildRows(array $elements, array $entity_map, array $columns, string $parent_id, int $offset, int $set_size, bool $reorder_blocked): array {
    $rows = [];
    foreach (array_values($elements) as $index => $element) {
      $link = $element->link;
      $rows[] = [
        '#theme' => 'menu_tree_ui_row',
        '#element' => $element,
        '#parent_id' => $parent_id,
        '#url' => $link->getUrlObject()->toString(),
        '#linked' => $this->isLinked($link),
        '#columns' => $this->buildColumnCells($element, $entity_map[$link->getPluginId()] ?? NULL, $columns),
        '#depth' => $parent_id === '' ? 1 : $element->depth,
        '#pos_in_set' => $offset + $index + 1,
        '#set_size' => $set_size,
        '#movable' => !$reorder_blocked && MenuLinkMovability::isMovable($link),
      ];
    }
    return $rows;
  }

  /**
   * Builds the cell render arrays of all active columns for one row.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement $element
   *   The tree element whose cells to build.
   * @param \Drupal\menu_link_content\MenuLinkContentInterface|null $entity
   *   The row's pre-loaded content entity, or NULL for a static/yml link.
   * @param \Drupal\treegrid\Plugin\TreegridColumn\TreegridColumnInterface[] $columns
   *   The active column plugins keyed by plugin ID.
   *
   * @return array
   *   Cell render arrays keyed by column plugin ID.
   */
  private function buildColumnCells(MenuLinkTreeElement $element, ?MenuLinkContentInterface $entity, array $columns): array {
    // A cell per active column, empty when there is no data for this row, so
    // every row matches the header's cell count and the columns stay aligned.
    $context = new MenuTreeRowContext($element, $entity);
    $cells = [];
    foreach ($columns as $col_id => $column) {
      $cells[$col_id] = $column->buildCell($context);
    }
    return $cells;
  }

  /**
   * Batch-loads menu_link_content entities for a set of tree elements.
   *
   * One IN query for all menu_link_content links in the level, keyed by
   * plugin ID, so column plugins (e.g. operations) never query per row.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $elements
   *   The tree elements to load content entities for.
   *
   * @return array
   *   menu_link_content entities keyed by plugin ID.
   */
  private function loadContentEntitiesForElements(array $elements): array {
    $uuids = [];
    foreach ($elements as $element) {
      $uuid = MenuTreeUiConstants::uuidFromPluginId($element->link->getPluginId());
      if ($uuid !== NULL) {
        $uuids[] = $uuid;
      }
    }
    if (!$uuids) {
      return [];
    }
    $storage = $this->entityTypeManager()->getStorage('menu_link_content');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('uuid', $uuids, 'IN')
      ->execute();
    if (!$ids) {
      return [];
    }
    $map = [];
    foreach ($storage->loadMultiple($ids) as $entity) {
      /** @var \Drupal\menu_link_content\MenuLinkContentInterface $entity */
      $map[MenuTreeUiConstants::CONTENT_PLUGIN_PREFIX . $entity->uuid()] = $entity;
    }
    return $map;
  }

}
