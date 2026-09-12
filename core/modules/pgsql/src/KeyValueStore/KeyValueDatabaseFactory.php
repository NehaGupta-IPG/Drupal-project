<?php

declare(strict_types=1);

namespace Drupal\pgsql\KeyValueStore;

use Drupal\Core\KeyValueStore\KeyValueDatabaseFactory as CoreKeyValueDatabaseFactory;

/**
 * Creates the PostgreSQL implementation of the database key/value storage.
 */
class KeyValueDatabaseFactory extends CoreKeyValueDatabaseFactory {

  /**
   * {@inheritdoc}
   */
  public function get($collection) {
    return new DatabaseStorage($collection, $this->serializer, $this->connection);
  }

}
