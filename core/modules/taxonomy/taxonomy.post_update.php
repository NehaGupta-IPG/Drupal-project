<?php

/**
 * @file
 * Post update functions for Taxonomy.
 */

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\taxonomy\Entity\Term;

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
 * Update views filters to use UUIDs for term IDs instead of entity IDs.
 */
function taxonomy_post_update_convert_term_views_filters_to_uuids(&$sandbox = NULL): void {
  if (!\Drupal::moduleHandler()->moduleExists('views')) {
    return;
  }

  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function ($view) {
    /** @var \Drupal\views\ViewEntityInterface $view */
    $displays = $view->get('display');

    foreach ($displays as &$display) {
      $filters = $display['display_options']['filters'] ?? [];
      foreach ($filters as $id => $filter) {
        if ($filter['plugin_id'] === 'taxonomy_index_tid' && isset($filter['value'])) {
          $filters[$id]['value'] = taxonomy_convert_entity_ids_to_uuids($filter['value']);
        }
      }

      $display['display_options']['filters'] = $filters;
    }
    $view->set('display', $displays);
    return TRUE;
  });
}

/**
 * Convert entity IDs to UUIDs.
 *
 * @param array $entity_ids
 *   Entity IDs to retrieve UUIDs for.
 *
 * @return array
 *   UUIDs for the given entity IDs.
 */
function taxonomy_convert_entity_ids_to_uuids(array $entity_ids): array {
  $uuids = [];

  foreach ($entity_ids as $entity_id) {
    if (Uuid::isValid($entity_id)) {
      // This ID is already a UUID.
      $uuids[] = $entity_id;
      continue;
    }
    $term = Term::load($entity_id);
    if ($term) {
      $uuids[] = $term->uuid();
    }
  }

  return $uuids;
}
