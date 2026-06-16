<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuTreeStorage;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_tree_ui\MenuLinkMovability;
use Drupal\menu_tree_ui\MenuTreeColumnManager;
use Drupal\menu_tree_ui\MenuTreeUiConstants;
use Drupal\menu_tree_ui\Service\MenuTreeLoader;
use Drupal\menu_tree_ui\Service\MenuTreeMover;
use Drupal\system\MenuInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes for the lazy-loaded menu tree UI.
 *
 * Five actions:
 *   - page():         full admin page, server-rendered top level + JS shell.
 *   - children():     JSON for one level (called on expand).
 *   - search():       HTML partial swapped in by htmx.
 *   - move():         drag-and-drop / keyboard reorder.
 *   - columnUpdate(): interactive column toggle (e.g. "expanded").
 */
final class MenuTreeController extends ControllerBase {

  public function __construct(
    private readonly MenuTreeLoader $loader,
    private readonly MenuTreeMover $mover,
    private readonly RendererInterface $renderer,
    private readonly MenuTreeColumnManager $columnManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('menu_tree_ui.loader'),
      $container->get('menu_tree_ui.mover'),
      $container->get('renderer'),
      $container->get('plugin.manager.menu_tree_column'),
    );
  }

  /**
   * Admin page for a single menu — server-renders the top level.
   */
  public function page(MenuInterface $menu): array {
    $top_level = $this->loader->loadLevel($menu->id(), NULL);
    $columns = $this->columnManager->getSortedColumns();
    $entity_map = $this->loadContentEntitiesForElements($top_level);

    $column_headers = [];
    foreach ($columns as $col_id => $column) {
      $column_headers[$col_id] = [
        'id' => $col_id,
        'label' => $column->getHeader(),
      ];
    }

    $rendered_rows = [];
    $set_size = count($top_level);
    $i = 0;
    foreach ($top_level as $element) {
      $rendered_rows[] = [
        '#theme' => 'menu_tree_ui_row',
        '#element' => $element,
        '#parent_id' => '',
        '#operations' => $this->renderOperations($element, $entity_map),
        '#columns' => $this->buildColumnCells($element, $columns),
        '#depth' => 1,
        '#pos_in_set' => ++$i,
        '#set_size' => $set_size,
        '#movable' => MenuLinkMovability::isMovable($element->link),
      ];
    }

    $move_url = Url::fromRoute('menu_tree_ui.move', ['menu' => $menu->id()])->toString();
    $column_update_url = Url::fromRoute('menu_tree_ui.column_update', ['menu' => $menu->id()])->toString();

    return [
      '#theme' => 'menu_tree_ui_page',
      '#menu' => $menu,
      '#top_level' => $rendered_rows,
      '#columns' => $column_headers,
      '#search_action' => Url::fromRoute('menu_tree_ui.search', ['menu' => $menu->id()])->toString(),
      '#children_action' => Url::fromRoute('menu_tree_ui.children', ['menu' => $menu->id()])->toString(),
      '#attached' => [
        'library' => ['menu_tree_ui/menu_tree_ui'],
        'drupalSettings' => [
          'menuTreeUi' => [
            'moveUrl' => $move_url,
            'columnUpdateUrl' => $column_update_url,
          ],
        ],
      ],
      '#cache' => [
        'contexts' => ['user.permissions', 'session'],
        'tags' => array_merge($menu->getCacheTags(), ['config:system.menu.' . $menu->id()]),
      ],
    ];
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
    $elements = $this->loader->loadLevel($menu->id(), $parent_id);
    $columns = $this->columnManager->getSortedColumns();
    $entity_map = $this->loadContentEntitiesForElements($elements);

    $data = [];
    foreach ($elements as $element) {
      $data[] = $this->serializeElement($element, $entity_map, $columns, $parent_id ?? '');
    }

    $response = new CacheableJsonResponse([
      'parent' => $parent_id,
      'items' => $data,
    ]);
    $cache = (new CacheableMetadata())
      ->addCacheTags($menu->getCacheTags())
      ->addCacheTags(['config:system.menu.' . $menu->id()])
      ->addCacheContexts(['user.permissions', 'url.query_args:parent']);
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
   * Applies a drag-and-drop move and returns the outcome as JSON.
   *
   * Body shape: { id, new_parent, before_id }.
   */
  public function move(MenuInterface $menu, Request $request): JsonResponse {
    $payload = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['ok' => FALSE, 'code' => 'bad_request', 'message' => 'JSON body required.'], 400);
    }

    $id = (string) ($payload['id'] ?? '');
    if ($id === '') {
      return new JsonResponse(['ok' => FALSE, 'code' => 'bad_request', 'message' => 'Missing id.'], 400);
    }
    $new_parent = (string) ($payload['new_parent'] ?? '');
    $before_id = $payload['before_id'] ?? NULL;
    if ($before_id !== NULL) {
      $before_id = (string) $before_id;
      if ($before_id === '') {
        $before_id = NULL;
      }
    }

    $result = $this->mover->move($menu->id(), $id, $new_parent, $before_id);
    return new JsonResponse($result, $result['ok'] ? 200 : 400);
  }

  /**
   * Applies an interactive column update and returns the outcome as JSON.
   *
   * Body shape: { column_id, link_id, value }.
   */
  public function columnUpdate(MenuInterface $menu, Request $request): JsonResponse {
    $payload = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['ok' => FALSE, 'code' => 'bad_request', 'message' => 'JSON body required.'], 400);
    }

    $column_id = (string) ($payload['column_id'] ?? '');
    $link_id = (string) ($payload['link_id'] ?? '');
    $value = $payload['value'] ?? NULL;

    if ($column_id === '' || $link_id === '') {
      return new JsonResponse(['ok' => FALSE, 'code' => 'bad_request', 'message' => 'Missing column_id or link_id.'], 400);
    }
    if (!$this->columnManager->hasDefinition($column_id)) {
      return new JsonResponse(['ok' => FALSE, 'code' => 'unknown_column', 'message' => 'Unknown column.'], 400);
    }
    if (!str_starts_with($link_id, MenuTreeUiConstants::CONTENT_PLUGIN_PREFIX)) {
      return new JsonResponse([
        'ok' => FALSE,
        'code' => 'not_updatable',
        'message' => 'Only user-created menu links can be updated.',
      ], 400);
    }

    $uuid = substr($link_id, strlen(MenuTreeUiConstants::CONTENT_PLUGIN_PREFIX));
    $storage = $this->entityTypeManager()->getStorage('menu_link_content');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('menu_name', $menu->id())
      ->condition('uuid', $uuid)
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return new JsonResponse(['ok' => FALSE, 'code' => 'not_found', 'message' => 'Link not found.'], 404);
    }

    /** @var \Drupal\menu_link_content\MenuLinkContentInterface $entity */
    $entity = $storage->load(reset($ids));
    $column = $this->columnManager->createInstance($column_id);

    if (!$column->handleUpdate($entity, $value)) {
      return new JsonResponse(['ok' => FALSE, 'code' => 'not_updatable', 'message' => 'Column is read-only.'], 400);
    }

    return new JsonResponse(['ok' => TRUE]);
  }

  // Private helpers.

  /**
   * Serializes a tree element to the JSON shape consumed by <menu-tree>.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement $element
   *   The tree element to serialize.
   * @param array<string, \Drupal\menu_link_content\MenuLinkContentInterface> $entity_map
   *   Pre-loaded entities keyed by plugin ID.
   * @param \Drupal\menu_tree_ui\Plugin\MenuTreeColumn\MenuTreeColumnInterface[] $columns
   *   The active column plugins keyed by plugin ID.
   * @param string $parent_id
   *   The parent link plugin ID, or '' for the top level.
   *
   * @return array
   *   The serialized row, matching the JSON shape consumed by <menu-tree>.
   */
  private function serializeElement(MenuLinkTreeElement $element, array $entity_map, array $columns, string $parent_id): array {
    $link = $element->link;

    $column_data = [];
    foreach ($columns as $col_id => $column) {
      $cell = $column->getCellData($element);
      if ($cell) {
        $column_data[$col_id] = $cell;
      }
    }

    return [
      'id' => $link->getPluginId(),
      'parent_id' => $parent_id,
      'title' => (string) $link->getTitle(),
      'enabled' => $link->isEnabled(),
      'has_children' => $element->hasChildren,
      'depth' => $element->depth,
      'url' => $link->getUrlObject()->toString(),
      'weight' => $link->getWeight(),
      'movable' => MenuLinkMovability::isMovable($link),
      'operations_html' => $this->renderOperationsHtml($element, $entity_map),
      'columns' => $column_data,
    ];
  }

  /**
   * Renders a row's operations as a finished HTML string.
   *
   * Children are built client-side by row-builder.js, but the dropbutton's
   * appearance is owned by the active admin theme's CSS (Claro, default_admin,
   * Gin, …), each of which expects different markup. Rather than have the JS
   * reproduce one theme's structure — and break under the others — the server
   * renders the operations with the active theme, exactly as the Twig-rendered
   * top level does, and the client injects the result verbatim. The dropbutton
   * library is already on the page from the top level, and
   * Drupal.attachBehaviors upgrades the injected markup, so behavior matches
   * the server-rendered rows.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement $element
   *   The tree element whose operations to render.
   * @param array<string, \Drupal\menu_link_content\MenuLinkContentInterface> $entity_map
   *   Pre-loaded entities keyed by plugin ID.
   *
   * @return string
   *   The rendered operations markup, or '' when there are no operations.
   */
  private function renderOperationsHtml(MenuLinkTreeElement $element, array $entity_map): string {
    $build = $this->renderOperations($element, $entity_map);
    if (empty($build['#links'])) {
      return '';
    }
    return (string) $this->renderer->renderInIsolation($build);
  }

  /**
   * Builds a #type 'operations' render array for a Twig-rendered row.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement $element
   *   The tree element whose operations to render.
   * @param array<string, \Drupal\menu_link_content\MenuLinkContentInterface> $entity_map
   *   Pre-loaded entities keyed by plugin ID.
   *
   * @return array
   *   The operations render array.
   */
  private function renderOperations(MenuLinkTreeElement $element, array $entity_map): array {
    return [
      '#type' => 'operations',
      '#links' => $this->buildOperationsLinks($element, $entity_map),
    ];
  }

  /**
   * Assembles the operations list for a row.
   *
   * Returns a keyed array of ['title', 'url', 'weight'] entries — the same
   * shape as hook_entity_operation(). Invokes
   * hook_menu_tree_ui_row_operations_alter() so third-party modules can add,
   * modify, or remove operations.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement $element
   *   The tree element whose operations to build.
   * @param array<string, \Drupal\menu_link_content\MenuLinkContentInterface> $entity_map
   *   Pre-loaded entities keyed by plugin ID.
   *
   * @return array
   *   Operations keyed by ID; each has 'title', 'url' and 'weight'.
   */
  private function buildOperationsLinks(MenuLinkTreeElement $element, array $entity_map): array {
    $link = $element->link;
    $operations = [];

    if ($edit_route = $link->getEditRoute()) {
      $operations['edit'] = [
        'title' => $this->t('Edit'),
        'url' => $edit_route,
        'weight' => 0,
      ];
    }

    $entity = $entity_map[$link->getPluginId()] ?? NULL;
    if ($entity instanceof MenuLinkContentInterface) {
      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'url' => $entity->toUrl('delete-form'),
        'weight' => 100,
      ];
    }

    // "Add child" mirrors menu_ui's MenuForm: a child sits one level deeper, so
    // offer it only while that stays within the materialized-path depth cap.
    if ($element->depth < MenuTreeStorage::MAX_DEPTH) {
      $operations['add-child'] = [
        'title' => $this->t('Add child'),
        'url' => Url::fromRoute(
          'entity.menu.add_link_form',
          ['menu' => $link->getMenuName()],
          ['query' => ['parent' => $link->getPluginId()]],
        ),
        'weight' => 20,
      ];
    }

    $this->moduleHandler()->alter('menu_tree_ui_row_operations', $operations, $element);

    // Every row action returns to this menu-tree page, regardless of how deep
    // the row is nested. Operation URLs otherwise inherit the *current request*
    // as their destination: harmless for the server-rendered top level, but a
    // row from the children endpoint would send the user back to its JSON
    // response after saving. Setting the destination explicitly keeps all
    // levels consistent.
    $destination = Url::fromRoute('menu_tree_ui.edit', ['menu' => $link->getMenuName()])->toString();
    foreach ($operations as &$operation) {
      if (($operation['url'] ?? NULL) instanceof Url) {
        $operation['url']->setOption('query', ['destination' => $destination] + ($operation['url']->getOption('query') ?? []));
      }
    }
    unset($operation);

    uasort($operations, static fn(array $a, array $b): int => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));
    return $operations;
  }

  /**
   * Builds cell data arrays for all active columns for one row.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement $element
   *   The tree element whose cells to build.
   * @param \Drupal\menu_tree_ui\Plugin\MenuTreeColumn\MenuTreeColumnInterface[] $columns
   *   The active column plugins keyed by plugin ID.
   *
   * @return array
   *   Cell data arrays keyed by column plugin ID.
   */
  private function buildColumnCells(MenuLinkTreeElement $element, array $columns): array {
    $cells = [];
    foreach ($columns as $col_id => $column) {
      $cell = $column->getCellData($element);
      if ($cell) {
        $cells[$col_id] = $cell;
      }
    }
    return $cells;
  }

  /**
   * Batch-loads menu_link_content entities for a set of tree elements.
   *
   * One IN query for all menu_link_content links in the level, keyed by
   * plugin ID. Avoids per-row queries in buildOperationsLinks().
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
      $plugin_id = $element->link->getPluginId();
      if (str_starts_with($plugin_id, MenuTreeUiConstants::CONTENT_PLUGIN_PREFIX)) {
        $uuids[] = substr($plugin_id, strlen(MenuTreeUiConstants::CONTENT_PLUGIN_PREFIX));
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
