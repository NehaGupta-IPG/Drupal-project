<?php

namespace Drupal\mongodb\Lock;

use Drupal\Core\Database\Connection;

/**
 * MongoDB implementation of \Drupal\Core\Lock\PersistentDatabaseLockBackend.
 */
class PersistentDatabaseLockBackend extends DatabaseLockBackend {

  /**
   * Constructs a new PersistentDatabaseLockBackend.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
    $this->lockId = 'persistent';
  }

}
