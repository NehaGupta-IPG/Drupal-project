<?php

/**
 * @file
 * Post update functions for Taxonomy.
 */

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
 * Re-save all terms to add a default created value.
 */
function taxonomy_post_update_populate_created_value(&$sandbox = NULL) {
  $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  if (!isset($sandbox['last_id'])) {
    $sandbox['current_tid'] = 0;
    $sandbox['processed'] = 0;
    $sandbox['total'] = $term_storage
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

  $limit = (int) Settings::get('entity_update_batch_size', 50);
  $ids = $term_storage->getQuery()
    ->accessCheck(FALSE)
    ->sort('tid')
    ->condition('tid', $sandbox['current_tid'], '>')
    ->range(0, $limit)
    ->execute();
  $terms = $term_storage->loadMultiple($ids);
  foreach ($terms as $term) {
    $unchanged = $term_storage->loadUnchanged($term->id());
    $term->setSyncing(TRUE);
    $term->created->value = $term->get('revision_created')->value ?? $unchanged->changed->value;
    $term->save();
    $sandbox['current_tid'] = $term->id();
  }
  $sandbox['processed'] += count($ids);
  $term_storage->resetCache($ids);
  $sandbox['#finished'] = ($sandbox['processed'] >= $sandbox['total']);
  return t('Taxonomy Term created dates populated. %total terms saved.', ['%total' => $sandbox['total']]);
}