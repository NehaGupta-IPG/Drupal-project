<?php

/**
 * @file
 * Test fixture.
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Serialization\Yaml;

$connection = Database::getConnection();

$connection->update('config')
  ->fields([
    'data' => serialize(Yaml::decode(file_get_contents('core/modules/file/tests/fixtures/config/core.entity_view_display.node.article.teaser.yml'))),
  ])
  ->condition('name', 'core.entity_view_display.node.article.teaser')
  ->execute();
