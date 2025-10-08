<?php
// @codingStandardsIgnoreFile

use Drupal\Core\Database\Database;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;

// cspell:ignore destid sourceid

$connection = Database::getConnection();

// Set the schema version.
$connection->merge('key_value')
  ->fields([
    'value' => 'i:10101;',
    'name' => 'migrate',
    'collection' => 'system.schema',
  ])
  ->condition('collection', 'system.schema')
  ->condition('name', 'migrate')
  ->execute();
$connection->merge('key_value')
  ->fields([
    'value' => 'i:8001;',
    'name' => 'migrate_update_test',
    'collection' => 'system.schema',
  ])
  ->condition('collection', 'system.schema')
  ->condition('name', 'migrate_update_test')
  ->execute();

// Update core.extension.
$extensions = $connection->select('config')
  ->fields('config', ['data'])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute()
  ->fetchField();
$extensions = unserialize($extensions);
$extensions['module']['migrate'] = 0;
$extensions['module']['migrate_update_test'] = 0;
$connection->update('config')
  ->fields([
    'data' => serialize($extensions),
    'collection' => '',
    'name' => 'core.extension',
  ])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute();

// Add all post updates as existing updates.
require_once __DIR__ . '/../../../migrate/migrate.post_update.php';
$existing_updates = $connection->select('key_value')
  ->fields('key_value', ['value'])
  ->condition('collection', 'post_update')
  ->condition('name', 'existing_updates')
  ->execute()
  ->fetchField();
$existing_updates = unserialize($existing_updates);
$existing_updates = array_merge(
  $existing_updates,
  array_keys(migrate_removed_post_updates())
);
$connection->update('key_value')
  ->fields(['value' => serialize($existing_updates)])
  ->condition('collection', 'post_update')
  ->condition('name', 'existing_updates')
  ->execute();


// Create migration table with legacy names.
$legacy_table_names = [
  // Migrations with short name are not changed.
  'migrate_map_short_name',
  'migrate_message_short_name',
  // A migration with a long name where only the message table is truncated.
  'migrate_map_migration_with_a_long_id_for_testing',
  // cSpell:disable-next-line
  'migrate_message_migration_with_a_long_id_for_testin',
  // A migration with a long name where both the map and the message table
  // are truncated.
  // cSpell:disable-next-line
  'migrate_map_migration_with_a_very_long_id_for_testi',
  'migrate_message_migration_with_a_very_long_id_for_t',
  // cSpell:disable-next-line
  'migrate_map_horizontal_horizontal_horizontal_horizo',
  'migrate_message_horizontal_horizontal_horizontal_ho',
];

foreach ($legacy_table_names as $legacy_table_name) {
  if (str_contains($legacy_table_name, 'map')) {
    $fields = [];
    $fields['source_ids_hash'] = [
      'type' => 'varchar',
      'length' => '64',
      'not null' => TRUE,
    ];
    $fields['sourceid1'] = [
      'type' => 'varchar',
      'length' => '255',
      'not null' => TRUE,
    ];
    $fields['destid1'] = [
      'type' => 'varchar',
      'length' => '255',
      'not null' => FALSE,
    ];
    $fields['source_row_status'] = [
      'type' => 'int',
      'size' => 'tiny',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'default' => MigrateIdMapInterface::STATUS_IMPORTED,
    ];
    $fields['rollback_action'] = [
      'type' => 'int',
      'size' => 'tiny',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'default' => MigrateIdMapInterface::ROLLBACK_DELETE,
    ];
    $fields['last_imported'] = [
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'default' => 0,
    ];
    $fields['hash'] = [
      'type' => 'varchar',
      'length' => '64',
      'not null' => FALSE,
    ];
    $schema = [
      'description' => '',
      'fields' => $fields,
      'primary key' => ['source_ids_hash'],
    ];
    $connection->schema()->createTable($legacy_table_name, $schema);

    $rows = [
      [
        'source_ids_hash' => '10',
        'sourceid1' => '1',
        'destid1' => '1',
        'source_row_status' => '0',
        'rollback_action' => '1',
        'last_imported' => '0',
        'hash' => '',
      ],
      [
        'source_ids_hash' => '20',
        'sourceid1' => '2',
        'destid1' => '2',
        'source_row_status' => '0',
        'rollback_action' => '0',
        'last_imported' => '0',
        'hash' => '',
      ],
      [
        'source_ids_hash' => '30',
        'sourceid1' => '3',
        'destid1' => '3',
        'source_row_status' => '0',
        'rollback_action' => '1',
        'last_imported' => '0',
        'hash' => '',
      ],
      [
        'source_ids_hash' => '40',
        'sourceid1' => '4',
        'destid1' => '4',
        'source_row_status' => '0',
        'rollback_action' => '1',
        'last_imported' => '0',
        'hash' => '',
      ],
      [
        'source_ids_hash' => '50',
        'sourceid1' => '5',
        'destid1' => '5',
        'source_row_status' => '0',
        'rollback_action' => '1',
        'last_imported' => '0',
        'hash' => '',
      ],
      [
        'source_ids_hash' => '60',
        'sourceid1' => '6',
        'destid1' => '6',
        'source_row_status' => '0',
        'rollback_action' => '0',
        'last_imported' => '0',
        'hash' => '',
      ],
    ];
    foreach ($rows as $row) {
      $connection->insert($legacy_table_name)->fields($row)->execute();
    }
  }

  if (str_contains($legacy_table_name, 'message')) {
    $fields = [];
    $fields['msgid'] = [
      'type' => 'serial',
      'unsigned' => TRUE,
      'not null' => TRUE,
    ];
    $fields['source_ids_hash'] = [
      'type' => 'varchar',
      'length' => '64',
      'not null' => TRUE,
    ];
    $fields['level'] = [
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'default' => 1,
    ];
    $fields['message'] = [
      'type' => 'text',
      'size' => 'medium',
      'not null' => TRUE,
    ];
    $schema = [
      'description' => '',
      'fields' => $fields,
      'primary key' => ['msgid'],
    ];
    $connection->schema()->createTable($legacy_table_name, $schema);

    $rows = [
      [
        'msgid' => '1',
        'source_ids_hash' => '10',
        'level' => (string) MigrationInterface::MESSAGE_ERROR,
        'message' => '',
      ],
      [
        'msgid' => '2',
        'source_ids_hash' => '20',
        'level' => (string) MigrationInterface::MESSAGE_ERROR,
        'message' => '',
      ],
      [
        'msgid' => '3',
        'source_ids_hash' => '30',
        'level' => (string) MigrationInterface::MESSAGE_ERROR,
        'message' => '',
      ],
      [
        'msgid' => '4',
        'source_ids_hash' => '40',
        'level' => (string) MigrationInterface::MESSAGE_INFORMATIONAL,
        'message' => '',
      ],
    ];
    foreach ($rows as $row) {
      $connection->insert($legacy_table_name)->fields($row)->execute();
    }
  }
}

