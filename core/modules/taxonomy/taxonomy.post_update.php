<?php

/**
 * @file
 * Post update functions for Taxonomy.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\views\ViewEntityInterface;
use Drupal\views\ViewsConfigUpdater;

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

  /** @var \Drupal\views\ViewsConfigUpdater $view_config_updater */
  $view_config_updater = \Drupal::service(ViewsConfigUpdater::class);
  $view_config_updater->setDeprecationsEnabled(FALSE);
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view) use ($view_config_updater): bool {
    return $view_config_updater->needsTaxonomyTermViewsFilterUpdate($view);
  });
}
