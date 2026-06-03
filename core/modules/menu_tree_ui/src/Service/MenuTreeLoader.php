<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Menu\MenuTreeStorageInterface;

/**
 * Loads slices of a menu tree for lazy-loaded admin UI consumption.
 *
 * LoadLevel() is unchanged. search() is a thin orchestrator: it collects
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
   */
  public function __construct(
    private readonly MenuLinkTreeInterface $menuTree,
    private readonly MenuTreeStorageInterface $treeStorage,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly iterable $searchProviders,
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
