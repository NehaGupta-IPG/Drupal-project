<?php

/**
 * @file
 * Hooks provided by the Menu tree UI module.
 */

use Drupal\Core\Menu\MenuLinkTreeElement;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alters a single loaded level of a menu tree.
 *
 * Fires at the end of \Drupal\menu_tree_ui\Service\MenuTreeLoader::loadLevel(),
 * once per level fetched — the initial page load and every lazy "expand" fetch
 * call it separately, each time with only that level's elements. Implement
 * this to add, remove, or reorder tree elements at a level, or to react to
 * which level was requested.
 *
 * @param \Drupal\Core\Menu\MenuLinkTreeElement[] &$elements
 *   The level's tree elements, access-checked and sorted by weight.
 * @param string $menu_name
 *   Menu entity ID (e.g. 'main', 'admin').
 * @param string|null $parent_id
 *   Plugin ID of the parent whose children were loaded, or NULL for the menu's
 *   top level.
 *
 * @see \Drupal\menu_tree_ui\Service\MenuTreeLoader::loadLevel()
 */
function hook_menu_tree_ui_level_alter(array &$elements, string $menu_name, ?string $parent_id) {
  // Hide a specific link from the tree UI without removing it from the menu.
  foreach ($elements as $key => $element) {
    if ($element->link->getPluginId() === 'my_module.hidden_link') {
      unset($elements[$key]);
    }
  }
}

/**
 * Alters the merged results of a menu tree search.
 *
 * Fires at the end of \Drupal\menu_tree_ui\Service\MenuTreeLoader::search(),
 * after results from all tagged menu_tree_ui.search_provider services have
 * been merged, deduplicated by link plugin ID, and sliced to the requested
 * limit. Implement this to add, remove, or reorder search results.
 *
 * Each result item is an array with keys 'id' (the link's plugin ID), 'title',
 * 'enabled' (bool), 'edit_url' (string or NULL), 'breadcrumb' (string[] of
 * display titles from the menu root down to the result), and
 * 'breadcrumb_ids' (string[] of the matching plugin IDs).
 *
 * @param array &$results
 *   The merged, deduplicated, limit-sliced result list.
 * @param string $menu_name
 *   Menu entity ID being searched.
 * @param string $query
 *   The search string, already trimmed.
 *
 * @see \Drupal\menu_tree_ui\Service\MenuTreeLoader::search()
 */
function hook_menu_tree_ui_search_results_alter(array &$results, string $menu_name, string $query) {
  // Drop results the current user is not allowed to see for some
  // module-specific reason not already covered by the tree's access check.
  foreach ($results as $key => $result) {
    if (str_starts_with($result['id'], 'my_module.restricted:')) {
      unset($results[$key]);
    }
  }
}

/**
 * Alters a menu link row's operations (Edit, Delete, Add child, …).
 *
 * Fires from \Drupal\menu_tree_ui\Plugin\TreegridColumn\OperationsColumn for
 * every row that has at least one operation. The operations array has the
 * same shape as hook_entity_operation(): keyed by machine-readable operation
 * ID, each entry has 'title', 'url', and 'weight'.
 *
 * @param array $operations
 *   Operations keyed by ID; each has 'title' (translatable string), 'url'
 *   (a \Drupal\Core\Url), and 'weight' (int).
 * @param \Drupal\Core\Menu\MenuLinkTreeElement $element
 *   The tree element the row represents.
 *
 * @see \Drupal\menu_tree_ui\Plugin\TreegridColumn\OperationsColumn
 */
function hook_menu_tree_ui_row_operations_alter(array &$operations, MenuLinkTreeElement $element) {
  // Add a custom operation to every row.
  $operations['my_module_action'] = [
    'title' => t('Do something'),
    'url' => \Drupal\Core\Url::fromRoute('my_module.do_something', [
      'menu_link' => $element->link->getPluginId(),
    ]),
    'weight' => 50,
  ];
}

/**
 * Reacts to a menu link move that has just been written to storage.
 *
 * Fires from \Drupal\menu_tree_ui\Service\MenuTreeMover::applyMove(), once the
 * move has been persisted through the menu link manager (and destination
 * siblings renumbered). Implementations receive the final weight the moved
 * link was given; the parent is $new_parent_id, exactly as validated. It fires
 * once per move, after that move is written during Save.
 *
 * @param string $menu_name
 *   The menu the move happened within.
 * @param string $moved_id
 *   Plugin ID of the link that was moved.
 * @param string $new_parent_id
 *   Plugin ID of the new parent, or empty string for the menu root.
 * @param int $weight
 *   The weight the moved link was given among its new siblings.
 *
 * @see \Drupal\menu_tree_ui\Service\MenuTreeMover::applyMove()
 */
function hook_menu_tree_ui_post_move(string $menu_name, string $moved_id, string $new_parent_id, int $weight) {
  // Log moves of a specific link for an audit trail.
  if ($moved_id === 'my_module.audited_link') {
    \Drupal::logger('my_module')->info('Link %id moved to parent %parent at weight %weight in menu %menu.', [
      '%id' => $moved_id,
      '%parent' => $new_parent_id,
      '%weight' => $weight,
      '%menu' => $menu_name,
    ]);
  }
}

/**
 * @} End of "addtogroup hooks".
 */
