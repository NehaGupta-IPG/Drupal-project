<?php

/**
 * @file
 * Adds string and language formatters without tag settings to the database.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();
$field_uuids = [
  'string.article' => '843f42f6-25bd-45ba-9c9d-b38490e976ae',
  'string.page' => 'd4f30489-087d-463c-9f90-bc39d47f9842',
  'language.article' => 'bc0ad996-182a-452d-a1c7-8f7caab22d98',
  'language.page' => '8310ea15-eab3-4f6b-aa68-b54d95fe928b',
];
// Insert raw configuration so current formatter defaults are not applied.
// The update test installs the field schema and last-installed definitions
// after bootstrapping Drupal with this historical configuration.
foreach (['string', 'language'] as $type) {
  $field_name = "field_{$type}_tag";
  $storage = [
    'uuid' => $type === 'string' ? '07040892-5fd0-477a-b9b2-0c20ae9c308a' : 'bc55b12b-c967-4645-9d16-4db5b84ab6b4',
    'langcode' => 'en',
    'status' => TRUE,
    'dependencies' => ['module' => ['node']],
    'id' => "node.$field_name",
    'field_name' => $field_name,
    'entity_type' => 'node',
    'type' => $type,
    'settings' => $type === 'string' ? ['max_length' => 255, 'is_ascii' => FALSE, 'case_sensitive' => FALSE] : [],
    'module' => 'core',
    'locked' => FALSE,
    'cardinality' => -1,
    'translatable' => TRUE,
    'indexes' => [],
    'persist_with_no_fields' => FALSE,
    'custom_storage' => FALSE,
  ];
  $connection->insert('config')->fields([
    'collection' => '',
    'name' => "field.storage.node.$field_name",
    'data' => serialize($storage),
  ])->execute();
  foreach (['article', 'page'] as $bundle) {
    $field = [
      'uuid' => $field_uuids["$type.$bundle"],
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => ['config' => ["field.storage.node.$field_name", "node.type.$bundle"]],
      'id' => "node.$bundle.$field_name",
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => $bundle,
      'label' => "Test $type",
      'description' => '',
      'required' => FALSE,
      'translatable' => TRUE,
      'default_value' => [],
      'default_value_callback' => '',
      'settings' => [],
      'field_type' => $type,
    ];
    $connection->insert('config')->fields([
      'collection' => '',
      'name' => "field.field.node.$bundle.$field_name",
      'data' => serialize($field),
    ])->execute();
  }
}
foreach (['node.article.default', 'node.article.teaser', 'node.page.default'] as $id) {
  $name = "core.entity_view_display.$id";
  $data = $connection->select('config', 'c')
    ->fields('c', ['data'])
    ->condition('collection', '')
    ->condition('name', $name)
    ->execute()
    ->fetchField();
  $display = unserialize($data);
  $display['content']['field_string_tag'] = [
    'type' => 'string',
    'label' => 'hidden',
    'settings' => [
      'link_to_entity' => TRUE,
      'link_rel' => 'edit-form',
    ],
    'third_party_settings' => [],
    'weight' => -10,
    'region' => 'content',
  ];
  $display['content']['field_language_tag'] = [
    'type' => 'language',
    'label' => 'hidden',
    'settings' => [
      'link_to_entity' => FALSE,
      'link_rel' => 'canonical',
      'native_language' => TRUE,
    ],
    'third_party_settings' => [],
    'weight' => -9,
    'region' => 'content',
  ];
  unset($display['hidden']['field_string_tag'], $display['hidden']['field_language_tag']);
  if ($id === 'node.page.default') {
    $display['content']['field_string_tag']['settings']['tag'] = 'h2';
    $display['content']['field_language_tag']['settings']['tag'] = '';
  }
  $connection->update('config')
    ->fields(['data' => serialize($display)])
    ->condition('collection', '')
    ->condition('name', $name)
    ->execute();
}
