<?php

declare(strict_types=1);

namespace Drupal\pgsql\KeyValueStore;

use Drupal\Core\KeyValueStore\DatabaseStorage as CoreDatabaseStorage;

/**
 * PostgreSQL implementation of the database key/value storage.
 *
 * @see \Drupal\pgsql\TableVerifyTrait
 */
class DatabaseStorage extends CoreDatabaseStorage {

  use DatabaseStorageTrait;

}
