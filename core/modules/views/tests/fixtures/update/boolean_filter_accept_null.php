<?php

/**
 * @file
 * Test fixture.
 */

declare(strict_types=1);

use Drupal\Core\Database\Database;
use Drupal\Component\Serialization\Yaml;

$connection = Database::getConnection();

$connection->insert('config')
  ->fields([
    'collection' => '',
    'name' => 'views.view.test_boolean_filter_accept_null',
    'data' => serialize(Yaml::decode(file_get_contents('core/modules/taxonomy/tests/modules/taxonomy_test_views/test_views/views.view.test_boolean_filter_accept_null.yml'))),
  ])
  ->execute();
