<?php

/**
 * @file
 * Sets up testing for the 112002 update.
 */

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Database\Database;

$connection = Database::getConnection();

// Import YML config files.
$configs = [];
$configs[] = Yaml::decode(file_get_contents(__DIR__ . '/views.view.watchdog.yml'));

// Save them in the database.
foreach ($configs as $config) {
  $connection->insert('config')
    ->fields([
      'collection',
      'name',
      'data',
    ])
    ->values([
      'collection' => '',
      'name' => $config['id'],
      'data' => serialize($config),
    ])
    ->execute();
}
