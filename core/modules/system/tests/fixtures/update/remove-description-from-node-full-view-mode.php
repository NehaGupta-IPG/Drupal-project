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
$data['description'] = "\n";
$connection->update('config')
  ->condition('name', 'core.entity_view_mode.node.full')
  ->fields([
    'data' => serialize($data),
  ])
  ->execute();
