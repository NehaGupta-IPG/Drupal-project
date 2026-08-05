<?php

/**
 * @file
 * Enables the responsive_image module.
 *
 * The rest of the pre-update state (the entity type installation and the
 * view display component) is set up in
 * \Drupal\Tests\responsive_image\Functional\Update\ResponsiveImageAddFetchpriorityUpdateTest::setUp(),
 * since it needs a fully bootstrapped container.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

// Enable the responsive_image module.
$extensions = $connection->select('config')
  ->fields('config', ['data'])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute()
  ->fetchField();
$extensions = unserialize($extensions);
$extensions['module']['responsive_image'] = 0;
$connection->update('config')
  ->fields(['data' => serialize($extensions)])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute();

// Set the schema version so the module is not seen as missing updates.
$connection->merge('key_value')
  ->condition('collection', 'system.schema')
  ->condition('name', 'responsive_image')
  ->fields([
    'collection' => 'system.schema',
    'name' => 'responsive_image',
    'value' => 'i:8000;',
  ])
  ->execute();

// Mark the already removed post-updates as executed, so the module is not
// seen as too old to update. Do not include
// responsive_image_post_update_add_fetchpriority_setting(), because we want
// it to run.
$existing_updates = $connection->select('key_value', 'kv')
  ->fields('kv', ['value'])
  ->condition('collection', 'post_update')
  ->condition('name', 'existing_updates')
  ->execute()
  ->fetchField();
$existing_updates = $existing_updates ? unserialize($existing_updates) : [];
$existing_updates = array_merge($existing_updates, [
  'responsive_image_post_update_recreate_dependencies',
  'responsive_image_post_update_order_multiplier_numerically',
  'responsive_image_post_update_image_loading_attribute',
]);
$connection->merge('key_value')
  ->condition('collection', 'post_update')
  ->condition('name', 'existing_updates')
  ->fields([
    'collection' => 'post_update',
    'name' => 'existing_updates',
    'value' => serialize($existing_updates),
  ])
  ->execute();
