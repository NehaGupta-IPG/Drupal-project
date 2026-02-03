<?php

namespace Drupal\migrate;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Database\Connection;
use Drupal\migrate\Plugin\migrate\id_map\Sql;

/**
 * Provides class for updating migrate table names to a unique name.
 *
 * Only tables for currently available migrations are modified.
 */
class MigrateTableNamesUpdate {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The range length for the select query used to copy rows.
   *
   * @var int
   */
  protected int $length = 50;

  /**
   * Update migrate map and message tables.
   *
   * Migrate tables for discovered migrations are updated. If tables exist for
   * a migration that is not available then the table names will not be updated.
   */
  public function updateMapAndMessageTables(&$sandbox): ?FormattableMarkup {
    $this->database = \Drupal::service('database');

    if (empty($sandbox)) {
      if ($this->doSetup($sandbox) === TRUE) {
        $sandbox['#finished'] = 1;
        return NULL;
      }

      $sandbox['migrations_total'] = count($sandbox['migrations']);
      $sandbox['migrations_progress'] = 1;
      $sandbox['current_migration'] = array_pop($sandbox['migrations']);
      $sandbox['copied_table_names'] = [];
    }

    if (!empty($sandbox['current_migration'])) {
      $message = $this->doCurrentMigration($sandbox);

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
   * Initializes the sandbox.
   *
   * @return bool
   *   True if there is nothing to do, FALSE otherwise.
   */
  protected function doSetup(&$sandbox): bool {
    $current_map_table_names = $this->database->schema()->findTables('migrate_map%');
    $current_message_table_names = $this->database->schema()->findTables('migrate_message%');

    // If there are no tables there is nothing to do here.
    if (empty($current_map_table_names) && empty($current_message_table_names)) {
      return TRUE;
    }

    $migration_plugin_manager = \Drupal::service('plugin.manager.migration');
    $sandbox['migrations'] = $migration_plugin_manager->getDefinitions();
    // Use derived ID instead of base one.
    array_walk($sandbox['migrations'], function (&$definition, $migration_id) {
      $definition['id'] = $migration_id;
    });
    // Filter out non-SQL migration maps and those without a legacy table.
    array_filter($sandbox['migrations'], function ($definition) use ($migration_plugin_manager, $current_map_table_names) {
      [$legacy_map_table_name] = $this->getLegacyTableNames($definition['id']);
      $migration = $migration_plugin_manager->createStubMigration($definition);
      $id_map = $migration->getIdMap();
      return $id_map instanceof Sql &&
        in_array($legacy_map_table_name, $current_map_table_names, TRUE);
    });

    // If there are no remaining migrations to handle, we are done.
    if (empty($sandbox['migrations'])) {
      return TRUE;
    }

    // Identify duplicate table names. Use the message table name because it
    // is always longer and thus more likely to be truncated to a name
    // used by more that one migration.
    $tables = [];
    foreach ($sandbox['migrations'] as &$definition) {
      [$legacy_message_table_name] = $this->getLegacyTableNames($definition['id']);
      $tables[$legacy_message_table_name][] = $definition['id'];
    }
    $duplicates = array_filter($tables, fn ($value) => count($value) > 1);

    foreach ($sandbox['migrations'] as &$definition) {
      $migration = $migration_plugin_manager->createStubMigration($definition);
      $id_map = $migration->getIdMap();

      [$legacy_map_table_name, $legacy_message_table_name] = $this->getLegacyTableNames($definition['id']);

      // The message table name is always longer. So, if that has changed
      // then the tables either needs to be renamed or copied.
      if ($legacy_message_table_name === $id_map->messageTableName()) {
        // No action needed for the tables of this migration.
        unset($sandbox['migrations'][$definition['id']]);
        continue;
      }
      // Either rename or copy the tables for this migration.
      $definition['tables_names'] = [
        $legacy_map_table_name => $id_map->mapTableName(),
        $legacy_message_table_name => $id_map->messageTableName(),
      ];
      $definition['table_action'] = 'rename';
      // If this migration has a table name used by other migrations then
      // setup to copy the table.
      foreach ($duplicates as $duplicate) {
        if (in_array($definition['id'], $duplicate, TRUE)) {
          // Get the legacy row counts for each table.
          foreach (array_keys($definition['tables_names']) as $legacy_name) {
            $definition['tables_row_count'][$legacy_name] = $this->database->schema()
              ->tableExists($legacy_name)
              ? (int) $this->database->select($legacy_name)
                ->countQuery()
                ->execute()
                ->fetchField()
              : 0;
            $definition['tables_progress'][$legacy_name] = 0;
            $definition['table_action'] = 'copy';
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * Processes the tables for the current migration.
   */
  protected function doCurrentMigration(&$sandbox): ?FormattableMarkup {
    if (empty($sandbox['current_migration']['tables_names'])) {
      $message = new FormattableMarkup('Migration @mid - no changes', [
        '@mid' => $sandbox['current_migration']['id'],
      ]);
      $sandbox['current_migration'] = array_pop($sandbox['migrations']);
    }
    else {
      $action = $sandbox['current_migration']['table_action'];
      if ($action === 'copy') {
        // Create new tables.
        $migration = \Drupal::service('plugin.manager.migration')->createStubMigration($sandbox['current_migration']);
        $migration->getIdMap()->getDatabase();

        foreach ($sandbox['current_migration']['tables_names'] as $legacy_name => $new_name) {
          $progress = $sandbox['current_migration']['tables_progress'][$legacy_name];
          $total = $sandbox['current_migration']['tables_row_count'][$legacy_name];
          if ($progress >= $total) {
            continue;
          }

          $select_query = $this->database
            ->select($legacy_name, 't')
            ->fields('t')
            ->range($progress, $this->length);
          $this->database->insert($new_name)->from($select_query)->execute();
          $progress += $select_query->countQuery()->execute()->fetchField();
          $sandbox['current_migration']['tables_progress'][$legacy_name] = $progress;
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
      }
      if ($action === 'rename') {
        foreach ($sandbox['current_migration']['tables_names'] as $legacy_name => $new_name) {
          $this->database->schema()->renameTable($legacy_name, $new_name);
        }
        $message = new FormattableMarkup('Migration @mid - Rename tables', [
          '@mid' => $sandbox['current_migration']['id'],
        ]);
        $sandbox['current_migration'] = array_pop($sandbox['migrations']);
      }
    }
    return $message ?? NULL;
  }

  /**
   * Gets the legacy migrate table names.
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
    $prefix_length = strlen($this->database->getPrefix());
    $mapTableName = 'migrate_map_' . mb_strtolower($machine_name);
    $mapTableName = mb_substr($mapTableName, 0, 63 - $prefix_length);
    $messageTableName = 'migrate_message_' . mb_strtolower($machine_name);
    $messageTableName = mb_substr($messageTableName, 0, 63 - $prefix_length);
    return [$mapTableName, $messageTableName];
  }

}
