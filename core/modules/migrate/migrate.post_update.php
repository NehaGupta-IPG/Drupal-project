<?php

/**
 * @file
 * Post update functions for migrate.
 */

/**
 * Implements hook_removed_post_updates().
 */
function migrate_removed_post_updates(): array {
  return [
    'migrate_post_update_clear_migrate_source_count_cache' => '10.0.0',
  ];
}

/**
 * Ensures destination indexes exist for pre-existing migration map tables.
 */
function migrate_post_update_ensure_destination_id_map_indexes(): string {
  $database = \Drupal::database();
  $schema = $database->schema();
  $migration_plugin_manager = \Drupal::service('plugin.manager.migration');

  foreach ($migration_plugin_manager->createInstances([]) as $migration) {
    $id_map = $migration->getIdMap();
    if ($id_map instanceof \Drupal\migrate\Plugin\migrate\id_map\Sql && $schema->tableExists($id_map->mapTableName())) {
      $id_map->getDatabase();
    }
  }

  return 'Checked destination ID indexes for existing migration map tables.';
}
