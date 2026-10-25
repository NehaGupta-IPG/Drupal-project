<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_tree_ui\MenuLinkMovability;
use Drupal\menu_tree_ui\MenuTreeRowContext;
use Drupal\menu_tree_ui\MenuTreeUiConstants;
use Drupal\treegrid\TreegridColumnManager;
use Drupal\treegrid\TreegridCsrfToken;
use Drupal\menu_tree_ui\Service\MenuTreeLoader;
use Drupal\menu_tree_ui\Service\MenuTreeMover;
use Drupal\menu_tree_ui\Service\MenuTreeWorkingCopyLock;
use Drupal\treegrid_reorder\TreegridWorkingCopy;
use Drupal\system\MenuInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Routes for the lazy-loaded menu tree UI.
 *
 * Five actions:
 *   - page():         full admin page, server-rendered top level + JS shell.
 *   - children():     JSON for one level (called on expand).
 *   - search():       HTML partial swapped in by htmx.
 *   - move():         drag-and-drop / keyboard reorder.
 *   - columnUpdate(): interactive column toggle (e.g. "enabled").
 *
 * @internal
 */
final class MenuTreeController extends ControllerBase {

  public function __construct(
    private readonly MenuTreeLoader $loader,
    private readonly MenuTreeMover $mover,
    private readonly RendererInterface $renderer,
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
      $container->get('renderer'),
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
    $all_top_level = $this->loader->loadLevelForEditing($menu->id(), NULL);
    $top_level_total = count($all_top_level);
    // Render only the first page on the server; the client fetches the rest as
    // the editor scrolls. Slicing before the entity load and cell build bounds
    // the cost that scales with width (see MenuTreeUiConstants).
    $top_level = array_slice($all_top_level, 0, MenuTreeUiConstants::TOP_LEVEL_PAGE_SIZE);
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

    $rendered_rows = [];
    $set_size = $top_level_total;
    $i = 0;
    foreach ($top_level as $element) {
      $rendered_rows[] = [
        '#theme' => 'menu_tree_ui_row',
        '#element' => $element,
        '#parent_id' => '',
        '#url' => $element->link->getUrlObject()->toString(),
        '#linked' => $this->isLinked($element->link),
        '#columns' => $this->buildColumnCells($element, $entity_map[$element->link->getPluginId()] ?? NULL, $columns),
        '#depth' => 1,
        '#pos_in_set' => ++$i,
        '#set_size' => $set_size,
        '#movable' => !$reorder_blocked && MenuLinkMovability::isMovable($element->link),
      ];
    }

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
      '#attached' => [
        'library' => ['menu_tree_ui/menu_tree_ui'],
        'drupalSettings' => [
          'menuTreeUi' => [
            'moveUrl' => Url::fromRoute('menu_tree_ui.move', ['menu' => $menu_name])->toString(),
            'columnUpdateUrl' => Url::fromRoute('menu_tree_ui.column_update', ['menu' => $menu_name])->toString(),
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
   * Returns one level of children as JSON.
   *
   * Query params:
   *   - parent: plugin ID of the parent link, or empty for top level.
   */
  public function children(MenuInterface $menu, Request $request): CacheableJsonResponse {
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
    $elements = $this->loader->loadLevelForEditing($menu->id(), $parent_id);
    $total = count($elements);
    // Only the top level is paged; deeper levels return all their children so
    // the expand path, which does not page, is unaffected.
    $page = $parent_id === NULL
      ? array_slice($elements, $offset, MenuTreeUiConstants::TOP_LEVEL_PAGE_SIZE)
      : $elements;
    $columns = $this->columnManager->getSortedColumns();
    $entity_map = $this->loadContentEntitiesForElements($page);
    $reorder_blocked = $this->hasPendingRevisions($menu->id());

    $data = [];
    foreach ($page as $element) {
      $data[] = $this->serializeElement($element, $entity_map, $columns, $parent_id ?? '', $reorder_blocked);
    }

    $response = new CacheableJsonResponse([
      'parent' => $parent_id,
      'items' => $data,
      'offset' => $offset,
      'has_more' => $parent_id === NULL && ($offset + count($page) < $total),
      'total' => $total,
    ]);
    // Like ::page(), this level reflects the per-user working copy in
    // tempstore, which cache metadata cannot express, so it is not cached.
    // Without this a second editor sharing the same permissions could be
    // served the first editor's pending overlay from the dynamic page cache.
    $cache = (new CacheableMetadata())
      ->setCacheMaxAge(0)
      ->addCacheTags($menu->getCacheTags())
      ->addCacheTags(['config:system.menu.' . $menu->id()]);
    $response->addCacheableDependency($cache);
    return $response;
  }

  /**
   * Returns search results as an HTML partial (htmx-swappable).
   *
   * Note: any #attached assets in the render array are discarded here —
   * keep the menu_tree_ui_search_results theme hook asset-free.
   */
  public function search(MenuInterface $menu, Request $request): Response {
    $query = (string) $request->query->get('q', '');
    $results = $this->loader->search($menu->id(), $query);

    $build = [
      '#theme' => 'menu_tree_ui_search_results',
      '#results' => $results,
      '#query' => $query,
    ];
    $html = (string) $this->renderer->renderRoot($build);

    $response = new Response($html);
    $response->headers->set('Content-Type', 'text/html; charset=utf-8');
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    return $response;
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
      return new JsonResponse(['ok' => TRUE]);
    });
  }

  /**
   * Applies an interactive column update and returns the outcome as JSON.
   *
   * Body shape: { column_id, link_id, value }.
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

    $column_id = (string) ($payload['column_id'] ?? '');
    $link_id = (string) ($payload['link_id'] ?? '');
    $value = $payload['value'] ?? NULL;

    if ($column_id === '' || $link_id === '') {
      return new JsonResponse([
        'ok' => FALSE,
        'code' => 'bad_request',
        'message' => (string) $this->t('Missing column_id or link_id.'),
      ], 400);
    }
    if (!$this->columnManager->hasDefinition($column_id)) {
      return new JsonResponse([
        'ok' => FALSE,
        'code' => 'unknown_column',
        'message' => (string) $this->t('Unknown column.'),
      ], 400);
    }

    $link_definition = $this->menuLinkManager->getDefinition($link_id, FALSE);
    if (!$link_definition || $link_definition['menu_name'] !== $menu->id()) {
      // Access is checked for the route's menu only; without this, a link ID
      // from another menu would let a column update reach it.
      return new JsonResponse(['ok' => FALSE, 'code' => 'not_found', 'message' => (string) $this->t('Link not found.')], 404);
    }

    $column = $this->columnManager->createInstance($column_id);
    return $this->withMenuLock($menu->id(), function () use ($column, $link_id, $value): JsonResponse {
      if (!$column->handleUpdate($link_id, $value)) {
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
   * Serializes a tree element to the JSON shape consumed by <drupal-treegrid>.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement $element
   *   The tree element to serialize.
   * @param array<string, \Drupal\menu_link_content\MenuLinkContentInterface> $entity_map
   *   Pre-loaded entities keyed by plugin ID.
   * @param \Drupal\treegrid\Plugin\TreegridColumn\TreegridColumnInterface[] $columns
   *   The active column plugins keyed by plugin ID.
   * @param string $parent_id
   *   The parent link plugin ID, or '' for the top level.
   * @param bool $reorder_blocked
   *   Whether reordering is off for the whole menu.
   *
   * @return array
   *   The serialized row, in the JSON shape consumed by <drupal-treegrid>.
   */
  private function serializeElement(MenuLinkTreeElement $element, array $entity_map, array $columns, string $parent_id, bool $reorder_blocked): array {
    $link = $element->link;

    return [
      'id' => $link->getPluginId(),
      'parent_id' => $parent_id,
      'title' => (string) $link->getTitle(),
      'enabled' => $link->isEnabled(),
      'has_children' => $element->hasChildren,
      'depth' => $element->depth,
      'url' => $link->getUrlObject()->toString(),
      'linked' => $this->isLinked($link),
      'weight' => $link->getWeight(),
      'movable' => !$reorder_blocked && MenuLinkMovability::isMovable($link),
      'columns' => $this->buildColumnCells($element, $entity_map[$link->getPluginId()] ?? NULL, $columns),
    ];
  }

  /**
   * Builds cell data arrays for all active columns for one row.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement $element
   *   The tree element whose cells to build.
   * @param \Drupal\menu_link_content\MenuLinkContentInterface|null $entity
   *   The row's pre-loaded content entity, or NULL for a static/yml link.
   * @param \Drupal\treegrid\Plugin\TreegridColumn\TreegridColumnInterface[] $columns
   *   The active column plugins keyed by plugin ID.
   *
   * @return array
   *   Cell data arrays keyed by column plugin ID.
   */
  private function buildColumnCells(MenuLinkTreeElement $element, ?MenuLinkContentInterface $entity, array $columns): array {
    // A cell per active column, empty when there is no data for this row, so
    // every row matches the header's cell count and the columns stay aligned.
    $context = new MenuTreeRowContext($element, $entity);
    $cells = [];
    foreach ($columns as $col_id => $column) {
      $cells[$col_id] = $column->getCellData($context) ?: [];
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
