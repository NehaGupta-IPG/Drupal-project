<?php

/**
 * @file
 * Install text_with_summary module.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

$extensions = $connection->select('config')
  ->fields('config', ['data'])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute()
  ->fetchField();

if ($extensions) {
  $data = unserialize($extensions);
  if (!isset($data['module']['text_with_summary'])) {
    $data['module']['text_with_summary'] = 1;
    $connection->update('config')
      ->fields(['data' => serialize($data)])
      ->condition('collection', '')
      ->condition('name', 'core.extension')
      ->execute();
  }
}

// Set text_with_summary module schema version.
$connection->merge('key_value')
  ->condition('collection', 'system.schema')
  ->condition('name', 'text_with_summary')
  ->keys([
    'collection' => 'system.schema',
    'name' => 'text_with_summary',
  ])
  ->fields([
    'value' => serialize(8000),
  ])
  ->execute();
