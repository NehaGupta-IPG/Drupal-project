<?php

declare(strict_types=1);

namespace Drupal\pgsql\KeyValueStore;

use Drupal\Core\KeyValueStore\KeyValueDatabaseExpirableFactory as CoreKeyValueDatabaseExpirableFactory;

/**
 * Creates the PostgreSQL implementation of the expirable key/value storage.
 */
class KeyValueDatabaseExpirableFactory extends CoreKeyValueDatabaseExpirableFactory {

  /**
   * {@inheritdoc}
   */
  public function get($collection) {
    if (!isset($this->storages[$collection])) {
      $this->storages[$collection] = new DatabaseStorageExpirable($collection, $this->serializer, $this->connection, $this->time);
    }
    return $this->storages[$collection];
  }

}
