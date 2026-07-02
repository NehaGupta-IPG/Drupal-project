<?php

/**
 * @file
 * Hooks provided by the Tree grid module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alters TreegridColumn plugin definitions.
 *
 * Fires from \Drupal\treegrid\TreegridColumnManager, which discovers
 * TreegridColumn-attributed plugin classes under each module's
 * src/Plugin/TreegridColumn/ and then invokes this alter hook, as with any
 * Drupal plugin manager's alterInfo(). Implement this to modify, add, or
 * remove column definitions that a consumer module will discover through
 * \Drupal\treegrid\TreegridColumnManager::getSortedColumns().
 *
 * @param array[] &$definitions
 *   The column plugin definitions, keyed by plugin ID. Each definition has:
 *   - id: (string) The plugin ID.
 *   - title: (\Drupal\Core\StringTranslation\TranslatableMarkup) The column
 *     header label.
 *   - weight: (int) Sort weight; columns render left to right by ascending
 *     weight.
 *   - deriver: (string|null) The deriver class, for plugins with derivatives.
 *   - class: (string) The plugin implementation class.
 *   - provider: (string) The module that defines the plugin.
 *
 * @see \Drupal\treegrid\TreegridColumnManager
 * @see \Drupal\treegrid\Attribute\TreegridColumn
 */
function hook_treegrid_column_info_alter(array &$definitions) {
  // Remove a column added by another module.
  unset($definitions['some_other_module_column']);

  // Change the sort position of a column.
  if (isset($definitions['operations'])) {
    $definitions['operations']['weight'] = 200;
  }
}

/**
 * @} End of "addtogroup hooks".
 */
