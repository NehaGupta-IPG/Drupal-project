<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Menu\MenuTreeStorageInterface;
use Drupal\treegrid_reorder\TreegridWorkingCopy;

/**
 * Loads slices of a menu tree for lazy-loaded admin UI consumption.
 *
 * Read paths come in two forms. loadLevel() returns the live tree.
 * loadLevelForEditing() returns the live tree with the menu's pending
 * working-copy moves overlaid (see TreegridWorkingCopy), so the UI reflects
 * unsaved reorders without writing them; the overlay is bounded by the number
 * of edits, never the menu size. search() is a thin orchestrator: it collects
 * from all tagged MenuTreeSearchProviderInterface services, deduplicates, and
 * fires hook_menu_tree_ui_search_results_alter() before returning.
 */
final class MenuTreeLoader {

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
  ) {}

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
   * Loads a single level with the menu's pending working-copy moves applied.
   *
   * Same as loadLevel(), then overlays the recorded moves so the editing UI
   * shows unsaved reorders. The overlay is a pure replay of the ops against
   * this one level's membership and order, so its cost tracks the number of
   * edits, not the size of the menu — preserving the lazy-load profile.
   *
   * @param string $menu_name
   *   Menu entity ID (e.g. 'main', 'admin').
   * @param string|null $parent_id
   *   Plugin ID of the parent to load children of, or NULL for the top level.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   Tree elements at one level, with pending moves applied.
   */
  public function loadLevelForEditing(string $menu_name, ?string $parent_id = NULL): array {
    $elements = $this->loadLevel($menu_name, $parent_id);
    $ops = $this->workingCopy->getOps($menu_name);
    if ($ops === []) {
      return $elements;
    }
    return $this->applyOverlay($elements, $ops, $parent_id ?? '');
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
    $map = [];
    foreach ($this->workingCopy->getOps($menu_name) as $op) {
      if (($op['type'] ?? '') === 'move') {
        $moved = (string) ($op['id'] ?? '');
        if ($moved !== '') {
          $map[$moved] = (string) ($op['new_parent'] ?? '');
        }
      }
    }
    return $map;
  }

  /**
   * Replays pending move ops against one level's membership and order.
   *
   * Simulates the recorded moves sequentially on a working list that starts as
   * the live children of $parent_id: a move whose destination is this level
   * inserts (or repositions) the link before its before_id; a move of a link
   * currently in this level to anywhere else removes it. A link moved in from
   * elsewhere is materialized on demand.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $elements
   *   The live elements at this level, in display order.
   * @param array<int, array<string, mixed>> $ops
   *   The menu's pending ops, in recorded order.
   * @param string $parent_id
   *   The level's parent plugin ID, or '' for the top level.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   The overlaid elements, in display order.
   */
  private function applyOverlay(array $elements, array $ops, string $parent_id): array {
    $by_id = [];
    $order = [];
    foreach ($elements as $element) {
      $id = $element->link->getPluginId();
      $by_id[$id] = $element;
      $order[] = $id;
    }

    foreach ($ops as $op) {
      if (($op['type'] ?? '') !== 'move') {
        continue;
      }
      $moved = (string) ($op['id'] ?? '');
      $new_parent = (string) ($op['new_parent'] ?? '');
      $before_id = $op['before_id'] ?? NULL;
      $before_id = $before_id === NULL ? NULL : (string) $before_id;
      $in_list = in_array($moved, $order, TRUE);

      if ($new_parent === $parent_id) {
        if (!$in_list) {
          $element = $this->materializeElement($moved);
          if ($element === NULL) {
            continue;
          }
          $by_id[$moved] = $element;
        }
        $order = self::spliceBefore($order, $moved, $before_id);
      }
      elseif ($in_list) {
        $order = array_values(array_filter($order, static fn(string $id): bool => $id !== $moved));
        unset($by_id[$moved]);
      }
    }

    $result = [];
    foreach ($order as $id) {
      if (isset($by_id[$id])) {
        $result[] = $by_id[$id];
      }
    }
    return $result;
  }

  /**
   * Inserts a link id before another in an ordered list (or appends it).
   *
   * The moved id is removed if already present, then placed immediately before
   * $before_id, or at the end when there is no anchor or the anchor is absent.
   * Shared by the read-side overlay here and the write-side re-weight in
   * MenuTreeMover::renumberSiblings() so both produce the same order.
   *
   * @param string[] $order
   *   The current ordered ids.
   * @param string $moved
   *   The id to (re)place.
   * @param string|null $before_id
   *   The anchor id, or NULL to append.
   *
   * @return string[]
   *   The new order.
   */
  public static function spliceBefore(array $order, string $moved, ?string $before_id): array {
    $order = array_values(array_filter($order, static fn(string $id): bool => $id !== $moved));
    $out = [];
    $inserted = FALSE;
    foreach ($order as $id) {
      if (!$inserted && $before_id !== NULL && $id === $before_id) {
        $out[] = $moved;
        $inserted = TRUE;
      }
      $out[] = $id;
    }
    if (!$inserted) {
      $out[] = $moved;
    }
    return $out;
  }

  /**
   * Builds a tree element for a link moved in from another level.
   *
   * The link's own properties (title, route, access) are unaffected by a move,
   * so a freshly built element is sufficient; only parent/weight change, which
   * the overlay already encodes through placement.
   *
   * @param string $plugin_id
   *   The moved link's plugin ID.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement|null
   *   The element, or NULL when the link no longer exists.
   */
  private function materializeElement(string $plugin_id): ?MenuLinkTreeElement {
    if (!$this->menuLinkManager->hasDefinition($plugin_id)) {
      return NULL;
    }
    $link = $this->menuLinkManager->createInstance($plugin_id);
    $has_children = $this->treeStorage->getSubtreeHeight($plugin_id) > 1;
    return new MenuLinkTreeElement($link, $has_children, 1, FALSE, []);
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
