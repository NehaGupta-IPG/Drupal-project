<?php

/**
 * @file
 * Post update functions for Path Alias.
 */

/**
 * Implements hook_removed_post_updates().
 */
function path_alias_removed_post_updates(): array {
  return [
    'path_alias_post_update_drop_path_alias_status_index' => '11.0.0',
    'path_alias_post_update_update_path_alias_revision_indexes' => '12.0.0',
  ];
}

/**
 * Re-save all the path alias to removing trailing space and slash.
 */
function path_alias_post_update_removing_trailing_space_slash(&$sandbox) {
  $path_aliases = \Drupal::entityTypeManager()->getStorage('path_alias')->loadMultiple();

  // Use the sandbox to store the information needed to track progression.
  if (!isset($sandbox['current'])) {
    // The count of entities visited so far.
    $sandbox['current'] = 0;
    // Total entities that must be visited.
    $sandbox['max'] = count($path_aliases);
  }
  $path_aliases = array_slice($path_aliases, (int) $sandbox['current'], 50);

  /** @var \Drupal\path_alias\Entity\PathAlias $alias */
  foreach ($path_aliases as $alias) {
    $alias->save();
    $sandbox['current']++;
  }
  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['current'] / $sandbox['max']);

  if ($sandbox['#finished'] >= 1) {
    return t('The batch Path Alias to remove trailing space and slash is completed.');
  }
}
