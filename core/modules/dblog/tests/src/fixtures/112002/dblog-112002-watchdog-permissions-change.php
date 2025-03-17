<?php

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

// Import YML config files.
$configs = [];
$configs[] = \Drupal\Component\Serialization\Yaml::decode(file_get_contents(__DIR__ . '/views.view.watchdog.yml'));

// Save them in the database.
foreach ($configs as $config) {
  $connection->insert('config')
    ->fields(array(
      'collection',
      'name',
      'data',
    ))
    ->values(array(
      'collection' => '',
      'name' => $config['id'],
      'data' => serialize($config),
    ))
    ->execute();
}
