<?php

/**
 * @file
 * Removes the history module from a fixture database.
 *
 * @todo remove this when https://www.drupal.org/i/3569127 lands.
 */

use Drupal\Core\Database\Database;
use Symfony\Component\Routing\Route;

$connection = Database::getConnection();

$serializedRoute = $connection->select('router')
  ->fields('router', ['route'])
  ->condition('name', 'system.db_update')
  ->execute()
  ->fetchField();

// If the route does not have the _csrf_token requirement in the fixture
// it will trigger a deprecation.
$route = unserialize($serializedRoute);
assert($route instanceof Route);
$route->addRequirements(['_csrf_token' => 'FALSE']);

$connection->update('router')
  ->fields(['route' => serialize($route)])
  ->condition('name', 'system.db_update')
  ->execute();
