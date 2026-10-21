<?php

/**
 * @file
 * Post update functions for Taxonomy.
 */

use Drupal\Core\Site\Settings;

/**
 * Implements hook_removed_post_updates().
 */
function taxonomy_removed_post_updates(): array {
  return [
    'taxonomy_post_update_clear_views_data_cache' => '9.0.0',
    'taxonomy_post_update_clear_entity_bundle_field_definitions_cache' => '9.0.0',
    'taxonomy_post_update_handle_publishing_status_addition_in_views' => '9.0.0',
    'taxonomy_post_update_remove_hierarchy_from_vocabularies' => '9.0.0',
    'taxonomy_post_update_make_taxonomy_term_revisionable' => '9.0.0',
    'taxonomy_post_update_configure_status_field_widget' => '9.0.0',
    'taxonomy_post_update_clear_views_argument_validator_plugins_cache' => '10.0.0',
    'taxonomy_post_update_set_new_revision' => '11.0.0',
    'taxonomy_post_update_set_vocabulary_description_to_null' => '11.0.0',
  ];
}

/**
 * Populate taxonomy index new langcode column.
 */
function taxonomy_post_update_populate_taxonomy_index_langcode(&$sandbox = NULL): void {
  /** @var \Drupal\node\NodeStorageInterface $node_storage */
  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
  $database = \Drupal::database();

  if (!isset($sandbox['max'])) {
    $sandbox['max'] = $database->select('taxonomy_index')
      ->fields('taxonomy_index', ['nid'])
      ->distinct()
      ->countQuery()
      ->execute()
      ->fetchField();
    $sandbox['progress'] = 0;
    $sandbox['limit'] = Settings::get('entity_update_batch_size', 50);

    // Handle the case of 0 node to process.
    if ($sandbox['max'] == 0) {
      $sandbox['#finished'] = 1;
      return;
    }
  }

  // Retrieve the next group.
  $entity_ids = $database->select('taxonomy_index')
    ->fields('taxonomy_index', ['nid'])
    ->orderBy('nid')
    ->distinct()
    ->range($sandbox['progress'], $sandbox['limit'])
    ->execute()
    ->fetchCol();
  $nodes = $node_storage->loadMultiple($entity_ids);

  foreach ($nodes as $node) {
    // Manually call node_update hook to rebuild taxonomy index, as it's
    // not possible to do it otherwise.
    \Drupal::moduleHandler()
      ->invoke('taxonomy', 'node_update', [$node]);

    // Update our progress information.
    $sandbox['progress']++;
  }

  if ($sandbox['progress'] != $sandbox['max']) {
    $sandbox['#finished'] = ($sandbox['progress'] >= $sandbox['max']);
  }
  else {
    $sandbox['#finished'] = 1;
  }
}
