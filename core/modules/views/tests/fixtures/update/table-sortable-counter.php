<?php

/**
 * @file
 * Test fixture.
 */

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Database\Database;

$connection = Database::getConnection();
$config = Yaml::decode(file_get_contents(__DIR__ . '/views.view.test_table_sortable_counter.yml'));
$connection->insert('config')
  ->fields([
    'collection' => '',
    'name' => 'views.view.test_table_sortable_counter',
    'data' => serialize($config),
  ])
  ->execute();
