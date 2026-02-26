<?php

/**
 * @file
 * Contains database additions to drupal-8.bare.standard.php.gz.
 *
 * Used for testing the upgrade path of https://www.drupal.org/project/drupal/issues/2834546.
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Serialization\Yaml;

$connection = Database::getConnection();

// Override configuration for basic block_content default form display with
// the status field display_label set to false.
$config = Yaml::decode(file_get_contents(__DIR__ . '/core.entity_form_display.block_content.basic.default_2834546.yml'));
$connection->update('config')
  ->fields([
    'data' => serialize($config),
  ])
  ->condition('name', 'core.entity_form_display.block_content.basic.default')
  ->execute();
