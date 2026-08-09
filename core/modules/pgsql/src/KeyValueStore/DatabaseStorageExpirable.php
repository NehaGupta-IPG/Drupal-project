<?php

declare(strict_types=1);

namespace Drupal\pgsql\KeyValueStore;

use Drupal\Core\KeyValueStore\DatabaseStorageExpirable as CoreDatabaseStorageExpirable;

/**
 * PostgreSQL implementation of the expirable database key/value storage.
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
class DatabaseStorageExpirable extends CoreDatabaseStorageExpirable {

  use TableVerifyTrait;

  /**
   * {@inheritdoc}
   */
  public function setWithExpire($key, $value, $expire): void {
    $this->verifyTableInTransaction();
    parent::setWithExpire($key, $value, $expire);
  }

  /**
   * {@inheritdoc}
   */
  public function setWithExpireIfNotExists($key, $value, $expire) {
    $this->verifyTableInTransaction();
    return parent::setWithExpireIfNotExists($key, $value, $expire);
  }

}
