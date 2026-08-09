<?php

declare(strict_types=1);

namespace Drupal\pgsql\KeyValueStore;

use Drupal\Core\KeyValueStore\DatabaseStorage as CoreDatabaseStorage;

/**
 * PostgreSQL implementation of the database key/value storage.
 *
 * The key/value storage recovers from statement failures caused by a missing
 * key/value table by creating the table and retrying. On PostgreSQL a failed
 * statement aborts the whole enclosing transaction, so inside a transaction
 * this recovery would leave the transaction unusable. Instead of running
 * every statement with a savepoint, this implementation makes sure the
 * key/value table exists before it is used inside a transaction. Outside a
 * transaction a failed statement has no lasting effect and the recovery of
 * the parent class works unchanged.
 */
class DatabaseStorage extends CoreDatabaseStorage {

  use TableVerifyTrait;

}
