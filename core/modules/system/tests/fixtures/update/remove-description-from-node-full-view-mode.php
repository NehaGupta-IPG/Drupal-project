<?php

/**
 * @file
 * Empties the description of the node full view mode.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

$data = $connection->select('config')
  ->condition('name', 'core.entity_view_mode.node.full')
  ->fields('config', ['data'])
  ->execute()
  ->fetchField();
$data = unserialize($data);
// Change description from null to new line so that the update hook calls trim().
// @see system_post_update_convert_empty_string_entity_view_modes_to_null().
$data['description'] = "\n";
$connection->update('config')
  ->condition('name', 'core.entity_view_mode.node.full')
  ->fields([
    'data' => serialize($data),
  ])
  ->execute();
