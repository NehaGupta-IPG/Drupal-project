<?php

declare(strict_types=1);

namespace Drupal\pgsql\Lock;

use Drupal\Core\Lock\DatabaseLockBackend as CoreDatabaseLockBackend;

/**
 * PostgreSQL implementation of the database lock backend.
 *
 * @see \Drupal\pgsql\Lock\PgsqlLockBackendTrait
 */
class DatabaseLockBackend extends CoreDatabaseLockBackend {

  use PgsqlLockBackendTrait;

}
