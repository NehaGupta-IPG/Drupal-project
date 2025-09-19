<?php

/**
 * @file
 * Test fixture for the operator labels update test.
 *
 * @see \Drupal\Tests\views\Functional\Update\OperatorLabelsDefaultsTest::setDatabaseDumpFiles()
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Serialization\Yaml;

$connection = Database::getConnection();

$connection->insert('config')
  ->fields([
    'collection' => '',
    'name' => 'views.view.test_exposed_operator_label_update',
    'data' => serialize(Yaml::decode(file_get_contents('core/modules/views/tests/fixtures/update/views.view.test_exposed_operator_label_update.yml'))),
  ])
  ->execute();
