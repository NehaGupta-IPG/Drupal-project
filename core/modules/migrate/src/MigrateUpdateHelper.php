<?php

namespace Drupal\migrate;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Database\Connection;
use Drupal\migrate\Plugin\migrate\id_map\Sql;

/**
 * Helper for migrate update hook.
 */
class MigrateUpdateHelper {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Update migrate map and message tables.
   *
   * Migrate tables for discovered migrations are updated. If tables exist for
   * a migration that is not available then the table names will not be updated.
   */
  public function updateMapAndMessageTables(&$sandbox): ?FormattableMarkup {
    $this->database = \Drupal::service('database');
    /** @var \Drupal\migrate\Plugin\MigrationPluginManager $migration_plugin_manager */
    $migration_plugin_manager = \Drupal::service('plugin.manager.migration');

    if (empty($sandbox)) {
      $current_map_table_names = $this->database->schema()->findTables('migrate_map%');
      // If there are no tables there is nothing to do here.
      if (empty($current_map_table_names)) {
        $sandbox['#finished'] = 1;
        return NULL;
      }

      $sandbox['migrations'] = $migration_plugin_manager->getDefinitions();
      // Use derived ID instead of base one.
      array_walk($sandbox['migrations'], function (&$definition, $migration_id) {
        $definition['id'] = $migration_id;
      });
      // Filter out non-sql migration maps and those without a legacy table.
      array_filter($sandbox['migrations'], function ($definition) use ($migration_plugin_manager, $current_map_table_names) {
        [$legacy_map_table_name] = $this->getLegacyTableNames($definition['id']);
        $migration = $migration_plugin_manager->createStubMigration($definition);
        $id_map = $migration->getIdMap();
        return $id_map instanceof Sql &&
          in_array($legacy_map_table_name, $current_map_table_names, TRUE);
      });
      // If there are no remaining migrations to handle, we are done.
      if (empty($sandbox['migrations'])) {
        $sandbox['#finished'] = 1;
        return NULL;
      }

      foreach ($sandbox['migrations'] as &$definition) {
        $migration = $migration_plugin_manager->createStubMigration($definition);
        /** @var \Drupal\migrate\Plugin\migrate\id_map\Sql $id_map */
        $id_map = $migration->getIdMap();

        [$legacy_map_table_name, $legacy_message_table_name] = $this->getLegacyTableNames($definition['id']);

        // Create new tables.
        $id_map->getDatabase();

        // Get the table names mapping and the legacy rows count.
        $definition['tables_names'] = [
          $legacy_map_table_name => $id_map->mapTableName(),
          $legacy_message_table_name => $id_map->messageTableName(),
        ];
        foreach (array_keys($definition['tables_names']) as $legacy_name) {
          $definition['tables_row_count'][$legacy_name] = $this->database->schema()->tableExists($legacy_name)
            ? (int) $this->database->select($legacy_name)->countQuery()->execute()->fetchField()
            : 0;
          $definition['tables_progress'][$legacy_name] = 0;
        }
      }

      $sandbox['migrations_total'] = count($sandbox['migrations']);
      $sandbox['migrations_progress'] = 1;
      $sandbox['current_migration'] = array_pop($sandbox['migrations']);

      $sandbox['copied_table_names'] = [];
    }

    if (!empty($sandbox['current_migration'])) {
      foreach ($sandbox['current_migration']['tables_names'] as $legacy_name => $new_name) {
        $progress = &$sandbox['current_migration']['tables_progress'][$legacy_name];
        $total = $sandbox['current_migration']['tables_row_count'][$legacy_name];
        if ($progress >= $total) {
          continue;
        }

        $select_query = $this->database->select($legacy_name, 't')
          ->fields('t')
          ->range($progress, 50);
        $this->database->insert($new_name)->from($select_query)->execute();
        $progress += 50;
      }

      $message = new FormattableMarkup('Migration @mid - Copied @progress of @total rows', [
        '@mid' => $sandbox['current_migration']['id'],
        '@progress' => array_sum($sandbox['current_migration']['tables_progress']),
        '@total' => array_sum($sandbox['current_migration']['tables_row_count']),
      ]);

      if (array_sum($sandbox['current_migration']['tables_progress']) === array_sum($sandbox['current_migration']['tables_row_count'])) {
        $sandbox['copied_table_names'] = array_merge($sandbox['copied_table_names'], array_keys($sandbox['current_migration']['tables_names']));
        $sandbox['current_migration'] = array_pop($sandbox['migrations']);
        $sandbox['migrations_progress']++;
      }

      $sandbox['#finished'] = 0;
      return $message;
    }
    elseif (!empty($sandbox['copied_table_names'])) {
      $table_name = array_pop($sandbox['copied_table_names']);
      $this->database->schema()->dropTable($table_name);

      $sandbox['#finished'] = 0.5;
      return new FormattableMarkup('Removed legacy table @name', [
        '@name' => $table_name,
      ]);
    }

    $sandbox['#finished'] = 1;
    return NULL;
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
