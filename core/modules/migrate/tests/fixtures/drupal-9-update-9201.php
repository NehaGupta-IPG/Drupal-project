<?php
// @codingStandardsIgnoreFile

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

// Set the schema version.
$connection->merge('key_value')
  ->fields([
    'value' => 'i:8001;',
    'name' => 'migrate',
    'collection' => 'system.schema',
  ])
  ->condition('collection', 'system.schema')
  ->condition('name', 'migrate')
  ->execute();
$connection->merge('key_value')
  ->fields([
    'value' => 'i:9000;',
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
$extensions['module']['migrate'] = 8001;
$extensions['module']['migrate_update_test'] = 9000;
$connection->update('config')
  ->fields([
    'data' => serialize($extensions),
    'collection' => '',
    'name' => 'core.extension',
  ])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute();

// Create migration table with legacy names.
$legacy_table_names = [
  // Migrations with short name are not changed.
  'migrate_map_short_name',
  'migrate_message_short_name',
  // A migration with a long name where both the map and the message table
  // are truncated.
  // cSpell:disable-next-lilne
  'migrate_map_migration_with_a_very_long_id_for_testi',
  'migrate_message_migration_with_a_very_long_id_for_t',
  // A migration with a long name where only the message table is truncated.
  'migrate_map_migration_with_a_long_id_for_testing',
  // cSpell:disable-next-lilne
  'migrate_message_migration_with_a_long_id_for_testin',
];

foreach ($legacy_table_names as $legacy_table_name) {
  $connection->schema()->createTable($legacy_table_name, [
    'fields' => [
      'empty' => [
        'type' => 'int',
        'not null' => TRUE,
        'size' => 'normal',
        'default' => '0',
      ],
    ],
  ]);
}

