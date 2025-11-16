<?php

/**
 * @file
 * Contains database additions for testing the upgrade path.
 *
 * @see https://www.drupal.org/node/2856551
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

$connection->insert('config')
  ->fields([
    'collection',
    'name',
    'data',
  ])
  ->values([
    'collection' => '',
    'name' => 'core.entity_form_display.entity_test_update.entity_test_update.default',
    'data' => 'a:10:{s:4:"uuid";s:36:"46962322-0520-45af-8b03-3f7d6530a382";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:1:{i:0;s:79:"field.field.entity_test_update.entity_test_update.field_test_configurable_field";}s:6:"module";a:1:{i:0;s:18:"entity_test_update";}}s:2:"id";s:45:"entity_test_update.entity_test_update.default";s:16:"targetEntityType";s:18:"entity_test_update";s:6:"bundle";s:18:"entity_test_update";s:4:"mode";s:7:"default";s:7:"content";a:1:{s:11:"translation";a:4:{s:6:"weight";i:11;s:8:"settings";a:0:{}s:20:"third_party_settings";a:0:{}s:6:"region";s:7:"content";}}s:6:"hidden";a:1:{s:29:"field_test_configurable_field";b:1;}}',
  ])
  ->execute();
