<?php

/**
 * @file
 * Post update functions for File.
 */

use Drupal\views\Entity\View;

/**
 * Implements hook_removed_post_updates().
 */
function file_removed_post_updates(): array {
  return [
    'file_post_update_add_txt_if_allows_insecure_extensions' => '10.0.0',
    'file_post_update_add_permissions_to_roles' => '11.0.0',
    'file_post_update_add_default_filename_sanitization_configuration' => '11.0.0',
    'file_post_update_add_playsinline' => '12.0.0',
  ];
}

/**
 * Uses a single file_usage join on the file usage listing.
 *
 * The usage display joined file_usage twice: once through the required
 * relationship, and again because its fields used relationship "none".
 *
 * @see https://www.drupal.org/project/drupal/issues/2632372
 */
function file_post_update_file_usage_listing_relationship(): void {
  if (!\Drupal::moduleHandler()->moduleExists('views')) {
    return;
  }

  $view = View::load('files');
  if ($view === NULL) {
    return;
  }

  $displays = $view->get('display');
  $changed = FALSE;
  foreach ($displays as &$display) {
    $relationship = $display['display_options']['relationships']['fid'] ?? NULL;
    $has_file_usage_relationship = ($relationship['table'] ?? NULL) === 'file_managed'
      && ($relationship['field'] ?? NULL) === 'fid';
    if (!$has_file_usage_relationship || !isset($display['display_options']['fields'])) {
      continue;
    }
    foreach ($display['display_options']['fields'] as &$field) {
      $uses_implicit_join = ($field['table'] ?? NULL) === 'file_usage'
        && ($field['relationship'] ?? 'none') === 'none';
      if ($uses_implicit_join) {
        $field['relationship'] = 'fid';
        $changed = TRUE;
      }
    }
    unset($field);
  }
  unset($display);

  if ($changed) {
    $view->set('display', $displays);
    $view->save();
  }
}
