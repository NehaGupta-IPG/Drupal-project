<?php

/**
 * @file
 * Additional updates to database for testing the upgrade path.
 *
 * Contains database additions to drupal-11.3.0.bare.standard.php.gz for testing
 * the upgrade path of https://www.drupal.org/node/2637808.
 */

use Drupal\Core\Database\Database;
use Drupal\Component\Serialization\Yaml;

$connection = Database::getConnection();

// Add the language field to node page default view mode and enabled link to content.
$config = Yaml::decode(file_get_contents(__DIR__ . '/core.entity_view_display.node.page.default.yml'));

// Update node.page.default configuration.
$connection->merge('config')
  ->fields(['data' => serialize($config)])
  ->condition('name', 'core.entity_view_display.node.page.default')
  ->execute();
