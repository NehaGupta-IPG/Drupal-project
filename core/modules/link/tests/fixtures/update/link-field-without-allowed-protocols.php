<?php

/**
 * @file
 * Creates a link field and removes the allowed_protocols setting.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

$field_storage_data = [
  'uuid' => 'test-link-field-storage-uuid',
  'langcode' => 'en',
  'status' => TRUE,
  'dependencies' => [
    'module' => ['link', 'node'],
  ],
  'id' => 'node.field_test_link',
  'field_name' => 'field_test_link',
  'entity_type' => 'node',
  'type' => 'link',
  'settings' => [],
  'module' => 'link',
  'locked' => FALSE,
  'cardinality' => 1,
  'translatable' => TRUE,
  'indexes' => [],
  'persist_with_no_fields' => FALSE,
  'custom_storage' => FALSE,
];

$connection->insert('config')
  ->fields([
    'collection' => '',
    'name' => 'field.storage.node.field_test_link',
    'data' => serialize($field_storage_data),
  ])
  ->execute();

$field_config_data = [
  'uuid' => 'test-link-field-config-uuid',
  'langcode' => 'en',
  'status' => TRUE,
  'dependencies' => [
    'config' => [
      'field.storage.node.field_test_link',
      'node.type.article',
    ],
    'module' => ['link'],
  ],
  'id' => 'node.article.field_test_link',
  'field_name' => 'field_test_link',
  'entity_type' => 'node',
  'bundle' => 'article',
  'label' => 'Test Link',
  'description' => '',
  'required' => FALSE,
  'translatable' => FALSE,
  'default_value' => [],
  'default_value_callback' => '',
  'settings' => [
    'title' => 1,
    'link_type' => 17,
  ],
  'field_type' => 'link',
];

$connection->insert('config')
  ->fields([
    'collection' => '',
    'name' => 'field.field.node.article.field_test_link',
    'data' => serialize($field_config_data),
  ])
  ->execute();
