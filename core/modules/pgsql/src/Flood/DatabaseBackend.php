<?php

declare(strict_types=1);

namespace Drupal\pgsql\Flood;

use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Flood\DatabaseBackend as CoreDatabaseBackend;

/**
 * PostgreSQL implementation of the database flood backend.
 *
 * The flood backend recovers from statement failures caused by a missing
 * flood table by creating the table and retrying. On PostgreSQL a failed
 * statement aborts the whole enclosing transaction, so inside a transaction
 * this recovery would leave the transaction unusable. Instead of running
 * every statement with a savepoint, this implementation makes sure the flood
 * table exists before it is used inside a transaction. Outside a transaction
 * a failed statement has no lasting effect and the recovery of the parent
 * class works unchanged.
 */
class DatabaseBackend extends CoreDatabaseBackend {

  /**
   * Whether the flood table is known to exist.
   */
  protected bool $tableVerified = FALSE;

  /**
   * Makes sure the flood table exists before it is used in a transaction.
   */
  protected function verifyTableInTransaction(): void {
    if ($this->tableVerified || !$this->connection->inTransaction()) {
      return;
    }
    if (!$this->connection->schema()->tableExists(static::TABLE_NAME)) {
      // Another process can create the table at the same time. The savepoint
      // keeps the enclosing transaction usable when that race is lost.
      $savepoint = $this->connection->startTransaction();
      try {
        $this->connection->schema()->createTable(static::TABLE_NAME, $this->schemaDefinition());
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
  public function register($name, $window = 3600, $identifier = NULL) {
    $this->verifyTableInTransaction();
    parent::register($name, $window, $identifier);
  }

  /**
   * {@inheritdoc}
   */
  public function clear($name, $identifier = NULL) {
    $this->verifyTableInTransaction();
    parent::clear($name, $identifier);
  }

  /**
   * {@inheritdoc}
   */
  public function clearByPrefix(string $name, string $prefix): void {
    $this->verifyTableInTransaction();
    parent::clearByPrefix($name, $prefix);
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowed($name, $threshold, $window = 3600, $identifier = NULL) {
    $this->verifyTableInTransaction();
    return parent::isAllowed($name, $threshold, $window, $identifier);
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    $this->verifyTableInTransaction();
    parent::garbageCollection();
  }

}
