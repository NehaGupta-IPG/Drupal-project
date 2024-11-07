<?php

namespace Drupal\migrate;

/**
 * Helper for migrate update hook.
 */
class MigrateUpdateHelper {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Update migrate table names.
   *
   * Migrate tables for discovered migrations are updated. If tables exist for
   * a migration that is not available then the table names will not be updated.
   */
  public function updateTableNames() {
    /** @var \Drupal\Core\Database\Connection $this->database */
    $this->database = \Drupal::service('database');

    $current_map_table_names = $this->database->schema()->findTables('migrate_map%');

    // If there are no tables there is nothing to do here.
    if (empty($current_map_table_names)) {
      return;
    }

    /** @var \Drupal\migrate\Plugin\MigrationPluginManager $migration_plugin_manager */
    $migration_plugin_manager = \Drupal::service('plugin.manager.migration');
    $definitions = $migration_plugin_manager->getDefinitions();
    // Loop though all existing migrations and check if there are existing
    // tables for this migration. If so, then update the table names.
    foreach ($definitions as $definition) {
      $legacy_table_names = $this->getLegacyTableNames($definition['id']);
      if (in_array($legacy_table_names[0], $current_map_table_names)) {
        /** @var \Drupal\migrate\Plugin\Migration $migration */
        $migration = $migration_plugin_manager->createStubMigration($definition);
        $id_map = $migration->getIdMap();
        if ($legacy_table_names[0] != $id_map->mapTableName()) {
          $this->database->schema()
            ->renameTable($legacy_table_names[0], $id_map->mapTableName());
        }
        if ($legacy_table_names[1] != $id_map->messageTableName()) {
          $this->database->schema()
            ->renameTable($legacy_table_names[1], $id_map->messageTableName());
        }
      }
    }
  }

  /**
   * Helper to get the legacy migrate table names.
   *
   * @param string $id
   *   The migration plugin ID.
   *
   * @return array
   *   An indexed array with the map table name and the message table name.
   */
  protected function getLegacyTableNames($id) {
    // The legacy method for creating the migrate table names.
    $machine_name = str_replace(':', '__', $id);
    $prefix_length = strlen($this->database->tablePrefix());
    $mapTableName = 'migrate_map_' . mb_strtolower($machine_name);
    $mapTableName = mb_substr($mapTableName, 0, 63 - $prefix_length);
    $messageTableName = 'migrate_message_' . mb_strtolower($machine_name);
    $messageTableName = mb_substr($messageTableName, 0, 63 - $prefix_length);
    return [$mapTableName, $messageTableName];
  }

}
