<?php

declare(strict_types=1);

namespace Drupal\pgsql\KeyValueStore;

use Drupal\Core\KeyValueStore\DatabaseStorageExpirable as CoreDatabaseStorageExpirable;

/**
 * PostgreSQL implementation of the expirable database key/value storage.
 *
 * @see \Drupal\pgsql\TableVerifyTrait
 */
class DatabaseStorageExpirable extends CoreDatabaseStorageExpirable {

  use DatabaseStorageTrait;

  /**
   * {@inheritdoc}
   */
  public function setWithExpire($key, $value, $expire): void {
    $this->verifyTableInTransaction($this->table);
    parent::setWithExpire($key, $value, $expire);
  }

  /**
   * {@inheritdoc}
   */
  public function setWithExpireIfNotExists($key, $value, $expire) {
    $this->verifyTableInTransaction($this->table);
    return parent::setWithExpireIfNotExists($key, $value, $expire);
  }

}
