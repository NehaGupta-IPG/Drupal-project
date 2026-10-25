<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Menu\MenuTreeStorageInterface;
use Drupal\menu_tree_ui\LevelSlice;
use Drupal\treegrid_reorder\MoveLogReplay;
use Drupal\treegrid_reorder\TreegridWorkingCopy;

/**
 * Loads slices of a menu tree for lazy-loaded admin UI consumption.
 *
 * Read paths come in two forms. loadLevel() returns the live tree.
 * loadLevelForEditing() returns a slice of a level with the menu's pending
 * working-copy moves applied (see TreegridWorkingCopy), so the UI reflects
 * unsaved reorders without writing them; the replay is bounded by the number
 * of edits, never the menu size. search() is a thin orchestrator: it collects
 * from all tagged MenuTreeSearchProviderInterface services, deduplicates, and
 * fires hook_menu_tree_ui_search_results_alter() before returning.
 *
 * @internal
 */
final class MenuTreeLoader {

  /**
   * Stateless, so it is instantiated rather than injected.
   */
  private readonly MoveLogReplay $replay;

  /**
   * Constructs a MenuTreeLoader.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menuTree
   *   The menu link tree service.
   * @param \Drupal\Core\Menu\MenuTreeStorageInterface $treeStorage
   *   The menu tree storage.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param iterable<\Drupal\menu_tree_ui\SearchProvider\MenuTreeSearchProviderInterface> $searchProviders
   *   Tagged iterator: all services tagged menu_tree_ui.search_provider.
   * @param \Drupal\treegrid_reorder\TreegridWorkingCopy $workingCopy
   *   The per-menu working copy of pending moves.
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menuLinkManager
   *   The menu link manager, for materializing moved-in elements.
   */
  public function __construct(
    private readonly MenuLinkTreeInterface $menuTree,
    private readonly MenuTreeStorageInterface $treeStorage,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly iterable $searchProviders,
    private readonly TreegridWorkingCopy $workingCopy,
    private readonly MenuLinkManagerInterface $menuLinkManager,
  ) {
    $this->replay = new MoveLogReplay();
  }

  /**
   * Loads a single level of a menu.
   *
   * @param string $menu_name
   *   Menu entity ID (e.g. 'main', 'admin').
   * @param string|null $parent_id
   *   Plugin ID of the parent to load children of, or NULL for the top level.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   Tree elements at one level, access-checked and sorted by weight.
   */
  public function loadLevel(string $menu_name, ?string $parent_id = NULL): array {
    $params = new MenuTreeParameters();
    if ($parent_id !== NULL && $parent_id !== '') {
      $params->setRoot($parent_id)->excludeRoot();
    }
    $params->setMaxDepth(1);

    $tree = $this->menuTree->load($menu_name, $params);

    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);

    $elements = array_values(array_filter($tree, static fn(MenuLinkTreeElement $e) =>
      $e->access === NULL || $e->access->isAllowed()
    ));

    $this->moduleHandler->alter('menu_tree_ui_level', $elements, $menu_name, $parent_id);

    return $elements;
  }

  /**
   * Loads a slice of a level with the menu's pending moves applied.
   *
   * The ids come from loadLevel(), so access filtering and the level hook
   * behave as there. The recorded moves are replayed on those ids, so the cost
   * tracks the number of edits, then the result is counted and sliced and
   * elements are built for the slice only.
   *
   * @param string $menu_name
   *   Menu entity ID (e.g. 'main', 'admin').
   * @param string|null $parent_id
   *   Plugin ID of the parent to load children of, or NULL for the top level.
   * @param int $offset
   *   The index of the slice's first link in the level.
   * @param int|null $limit
   *   The most links to return, or NULL for the rest of the level.
   */
  public function loadLevelForEditing(string $menu_name, ?string $parent_id = NULL, int $offset = 0, ?int $limit = NULL): LevelSlice {
    $live = [];
    foreach ($this->loadLevel($menu_name, $parent_id) as $element) {
      $live[$element->link->getPluginId()] = $element;
    }
    $view = $this->workingCopy->view($menu_name);
    $existing_ids = array_values(array_filter(
      $view->pendingIds(),
      fn(string $id): bool => $this->menuLinkManager->hasDefinition($id),
    ));
    $ids = array_map('strval', $view->levelIds($parent_id ?? '', array_map('strval', array_keys($live)), $existing_ids));

    $slice_ids = array_slice($ids, $offset, $limit);
    $elements = [];
    foreach ($slice_ids as $id) {
      $element = $live[$id] ?? $this->materializeElement($id, $parent_id ?? '');
      if ($element !== NULL) {
        $elements[] = $element;
      }
    }
    return new LevelSlice($ids, $elements, count($ids), $ids[$offset + count($slice_ids)] ?? NULL);
  }

  /**
   * Returns the pending re-parent of each moved link, keyed by plugin ID.
   *
   * Collapses the menu's working-copy move ops into a map of link plugin ID to
   * its pending new parent (last move wins). Callers overlay this on live
   * parent lookups so validation reflects the tree the editor is looking at,
   * not just live storage. The root-level key ('') is never present.
   *
   * @param string $menu_name
   *   Menu entity ID.
   *
   * @return array<string, string>
   *   Plugin ID => pending new parent plugin ID ('' for the menu root).
   */
  public function getPendingParentMap(string $menu_name): array {
    return $this->replay->pendingParents($this->workingCopy->getOps($menu_name));
  }

  /**
   * Builds a tree element for a link moved in from another level.
   *
   * The link's own properties (title, route, access) are unaffected by a move,
   * so a freshly built element is sufficient; only parent/weight change, which
   * the overlay already encodes through placement. The depth is derived from
   * the destination parent's live root path rather than the link's own (now
   * stale) live depth, so OperationsColumn's depth-cap check sees where the
   * link is pending to land, not where it currently lives. This does not
   * account for the destination parent itself being pending-moved elsewhere in
   * the same working copy — a rare double-pending case left as an
   * approximation.
   *
   * @param string $plugin_id
   *   The moved link's plugin ID.
   * @param string $parent_id
   *   The destination parent's plugin ID, or '' for the menu root.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement|null
   *   The element, or NULL when the link no longer exists.
   */
  private function materializeElement(string $plugin_id, string $parent_id): ?MenuLinkTreeElement {
    if (!$this->menuLinkManager->hasDefinition($plugin_id)) {
      return NULL;
    }
    $link = $this->menuLinkManager->createInstance($plugin_id);
    $has_children = $this->treeStorage->getSubtreeHeight($plugin_id) > 1;
    $depth = $parent_id === '' ? 1 : count($this->treeStorage->getRootPathIds($parent_id)) + 1;
    return new MenuLinkTreeElement($link, $has_children, $depth, FALSE, []);
  }

  /**
   * Searches a menu by link title across all registered search providers.
   *
   * Providers run in tag priority order. Results are merged, deduplicated by
   * plugin ID (first-seen wins), and sliced to $limit before the alter hook
   * fires. hook_menu_tree_ui_search_results_alter() may add, remove, or
   * reorder entries after the fact.
   *
   * @return array<int, array{
   *   id: string,
   *   title: string,
   *   enabled: bool,
   *   edit_url: string|null,
   *   breadcrumb: string[],
   *   breadcrumb_ids: string[],
   *   }>
   */
  public function search(string $menu_name, string $query, int $limit = 25): array {
    $query = trim($query);
    if ($query === '') {
      return [];
    }

    $seen = [];
    $merged = [];
    foreach ($this->searchProviders as $provider) {
      foreach ($provider->search($menu_name, $query, $limit) as $result) {
        if (!isset($seen[$result['id']])) {
          $seen[$result['id']] = TRUE;
          $merged[] = $result;
        }
      }
    }

    $results = array_slice($merged, 0, $limit);

    $this->moduleHandler->alter('menu_tree_ui_search_results', $results, $menu_name, $query);

    return $results;
  }

}
