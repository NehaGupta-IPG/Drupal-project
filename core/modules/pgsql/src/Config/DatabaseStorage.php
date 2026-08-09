<?php

declare(strict_types=1);

namespace Drupal\pgsql\Config;

use Drupal\Core\Config\DatabaseStorage as CoreDatabaseStorage;
use Drupal\Core\Database\DatabaseException;

/**
 * PostgreSQL implementation of the database config storage.
 *
 * The config storage recovers from statement failures caused by a missing
 * config table by creating the table and retrying. On PostgreSQL a failed
 * statement aborts the whole enclosing transaction, so inside a transaction
 * this recovery would leave the transaction unusable. Instead of running
 * every statement with a savepoint, this implementation makes sure the
 * config table exists before it is used inside a transaction. Outside a
 * transaction a failed statement has no lasting effect and the recovery of
 * the parent class works unchanged.
 *
 * The parent createCollection() creates the collection with "new static()",
 * so collections created from this storage keep this implementation.
 */
class DatabaseStorage extends CoreDatabaseStorage {

  /**
   * Whether the config table is known to exist.
   */
  protected bool $tableVerified = FALSE;

  /**
   * Makes sure the config table exists before it is used in a transaction.
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
  public function exists($name) {
    $this->verifyTableInTransaction();
    return parent::exists($name);
  }

  /**
   * {@inheritdoc}
   */
  public function read($name) {
    $this->verifyTableInTransaction();
    return parent::read($name);
  }

  /**
   * {@inheritdoc}
   */
  public function readMultiple(array $names) {
    $this->verifyTableInTransaction();
    return parent::readMultiple($names);
  }

  /**
   * {@inheritdoc}
   */
  public function write($name, array $data) {
    $this->verifyTableInTransaction();
    return parent::write($name, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function listAll($prefix = '') {
    $this->verifyTableInTransaction();
    return parent::listAll($prefix);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll($prefix = '') {
    $this->verifyTableInTransaction();
    return parent::deleteAll($prefix);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllCollectionNames() {
    $this->verifyTableInTransaction();
    return parent::getAllCollectionNames();
  }

}
