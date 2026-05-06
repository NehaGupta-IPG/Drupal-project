<?php

/**
 * @file
 * Fixture file to test views_post_update_enable_views_grid_module().
 *
 * Creates a test view that uses the grid style plugin to verify the
 * automatic enabling of the views_grid module during updates.
 */

use Drupal\Core\Database\Database;

$db = Database::getConnection();

// Create a test view configuration that uses the grid style plugin.
$view_config = [
  'uuid' => '12345678-1234-1234-1234-123456789012',
  'langcode' => 'en',
  'status' => TRUE,
  'dependencies' => [
    'module' => ['views_test_data'],
  ],
  'id' => 'test_grid_upgrade',
  'label' => 'Test Grid Upgrade',
  'module' => 'views',
  'description' => 'A test view with grid style for upgrade path testing.',
  'tag' => '',
  'base_table' => 'views_test_data',
  'base_field' => 'id',
  'display' => [
    'default' => [
      'display_plugin' => 'default',
      'id' => 'default',
      'display_title' => 'Default',
      'position' => 0,
      'display_options' => [
        'title' => 'Test Grid View',
        'fields' => [
          'name' => [
            'id' => 'name',
            'table' => 'views_test_data',
            'field' => 'name',
            'plugin_id' => 'standard',
          ],
        ],
        'pager' => [
          'type' => 'none',
          'options' => ['offset' => 0],
        ],
        'style' => [
          'type' => 'grid',
          'options' => [
            'columns' => 3,
            'automatic_width' => TRUE,
            'alignment' => 'horizontal',
            'col_class_default' => TRUE,
            'col_class_custom' => '',
            'row_class_default' => TRUE,
            'row_class_custom' => '',
          ],
        ],
        'row' => [
          'type' => 'fields',
          'options' => [],
        ],
        'query' => [
          'type' => 'views_query',
          'options' => [],
        ],
      ],
    ],
    'page_1' => [
      'display_plugin' => 'page',
      'id' => 'page_1',
      'display_title' => 'Page',
      'position' => 1,
      'display_options' => [
        'path' => 'test-grid-upgrade',
      ],
    ],
  ],
];

// Insert the view configuration into the config table.
$db->insert('config')
  ->fields([
    'collection' => '',
    'name' => 'views.view.test_grid_upgrade',
    'data' => serialize($view_config),
  ])
  ->execute();

// Also make sure views_test_data module is enabled in core.extension.
$extensions = $db->select('config')
  ->fields('config', ['data'])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute()
  ->fetchField();

if ($extensions) {
  $extensions = unserialize($extensions);
  $extensions['module']['views_test_data'] = 0;
  ksort($extensions['module']);

  $db->update('config')
    ->fields(['data' => serialize($extensions)])
    ->condition('collection', '')
    ->condition('name', 'core.extension')
    ->execute();
}
