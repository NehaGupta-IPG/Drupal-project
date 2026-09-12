<?php

declare(strict_types=1);

namespace Drupal\pgsql\Lock;

use Drupal\Core\Lock\PersistentDatabaseLockBackend as CorePersistentDatabaseLockBackend;

/**
 * PostgreSQL implementation of the persistent database lock backend.
 *
 * @see \Drupal\pgsql\Lock\PgsqlLockBackendTrait
 */
class PersistentDatabaseLockBackend extends CorePersistentDatabaseLockBackend {

  use PgsqlLockBackendTrait;

}
