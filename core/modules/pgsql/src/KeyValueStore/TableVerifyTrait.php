<?php

declare(strict_types=1);

namespace Drupal\pgsql\KeyValueStore;

use Drupal\Core\Database\DatabaseException;

/**
 * Makes sure the key/value table exists before use in a transaction.
 *
 * The key/value storages recover from statement failures caused by a missing
 * key/value table by creating the table and retrying. On PostgreSQL a failed
 * statement aborts the whole enclosing transaction, so inside a transaction
 * this recovery would leave the transaction unusable. Instead of running
 * every statement with a savepoint, this trait makes sure the key/value
 * table exists before it is used inside a transaction. Outside a transaction
 * a failed statement has no lasting effect and the recovery of the parent
 * class works unchanged.
 *
 * The trait carries the pre-flight check and the overrides of the methods
 * that both key/value storages share, because the PostgreSQL storages extend
 * their own core class and cannot share the code through inheritance.
 */
trait TableVerifyTrait {

  /**
   * Whether the key/value table is known to exist.
   */
  protected bool $tableVerified = FALSE;

  /**
   * Makes sure the key/value table exists before use in a transaction.
   */
  protected function verifyTableInTransaction(): void {
    if ($this->tableVerified || !$this->connection->inTransaction()) {
      return;
    }
    if (!$this->connection->schema()->tableExists($this->table)) {
      // Another process can create the table at the same time. The savepoint
      // keeps the enclosing transaction usable when that race is lost.
      $savepoint = $this->connection->startTransaction();
      try {
        $this->connection->schema()->createTable($this->table, static::schemaDefinition());
        $savepoint->commitOrRelease();
      }
      catch (DatabaseException) {
        $savepoint->rollBack();
      }
    }
    $this->tableVerified = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function has($key) {
    $this->verifyTableInTransaction();
    return parent::has($key);
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(array $keys) {
    $this->verifyTableInTransaction();
    return parent::getMultiple($keys);
  }

  /**
   * {@inheritdoc}
   */
  public function getAll() {
    $this->verifyTableInTransaction();
    return parent::getAll();
  }

  /**
   * {@inheritdoc}
   */
  public function getAllKeys(): iterable {
    $this->verifyTableInTransaction();
    return parent::getAllKeys();
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value): void {
    $this->verifyTableInTransaction();
    parent::set($key, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $data): void {
    $this->verifyTableInTransaction();
    parent::setMultiple($data);
  }

  /**
   * {@inheritdoc}
   */
  public function setIfNotExists($key, $value) {
    $this->verifyTableInTransaction();
    return parent::setIfNotExists($key, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function rename($key, $new_key): void {
    $this->verifyTableInTransaction();
    parent::rename($key, $new_key);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $keys): void {
    $this->verifyTableInTransaction();
    parent::deleteMultiple($keys);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll(): void {
    $this->verifyTableInTransaction();
    parent::deleteAll();
  }

}
