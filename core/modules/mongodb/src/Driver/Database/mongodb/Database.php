<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\Database as CoreDatabase;

/**
 * The MongoDB implementation of \Drupal\Core\Database\Database.
 */
abstract class Database extends CoreDatabase {

  /**
   * Gets the connection object for the specified key to the admin database.
   *
   * @param string $key
   *   The database connection key. Defaults to NULL which means the active key.
   *
   * @return \Drupal\Core\Database\Connection
   *   The corresponding connection object.
   */
  final public static function getAdminConnection($key = NULL) {
    if (!isset($key)) {
      // By default, we want the active connection, set in setActiveConnection.
      $key = self::$activeKey;
    }

    if (empty(self::$databaseInfo[$key]['admin'])) {
      // Add the database info to the admin database.
      self::$databaseInfo[$key]['admin'] = self::$databaseInfo[$key]['default'];
      self::$databaseInfo[$key]['admin']['database'] = 'admin';
    }

    if (!isset(self::$connections[$key]['admin'])) {
      // If necessary, a new connection is opened.
      self::$connections[$key]['admin'] = self::openConnection($key, 'admin');
    }

    return self::$connections[$key]['admin'];
  }

}
