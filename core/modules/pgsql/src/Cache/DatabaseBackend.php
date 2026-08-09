<?php

declare(strict_types=1);

namespace Drupal\pgsql\Cache;

use Drupal\Core\Cache\DatabaseBackend as CoreDatabaseBackend;
use Drupal\Core\Database\DatabaseException;

/**
 * PostgreSQL implementation of the database cache backend.
 *
 * The cache backend recovers from statement failures caused by a missing
 * cache bin table by creating the table and retrying. On PostgreSQL a failed
 * statement aborts the whole enclosing transaction, so inside a transaction
 * this recovery would leave the transaction unusable. Instead of running
 * every statement with a savepoint, this implementation makes sure the bin
 * table exists before it is used inside a transaction. Outside a transaction
 * a failed statement has no lasting effect and the recovery of the parent
 * class works unchanged.
 */
class DatabaseBackend extends CoreDatabaseBackend {

  /**
   * Whether the bin table is known to exist.
   */
  protected bool $binVerified = FALSE;

  /**
   * Makes sure the bin table exists before it is used in a transaction.
   */
  protected function verifyBinInTransaction(): void {
    if ($this->binVerified || !$this->connection->inTransaction()) {
      return;
    }
    $schema = $this->connection->schema();
    if (!$schema->tableExists($this->bin)) {
      // Another process can create the table at the same time. The savepoint
      // keeps the enclosing transaction usable when that race is lost.
      $savepoint = $this->connection->startTransaction();
      try {
        $schema->createTable($this->bin, $this->schemaDefinition());
        $savepoint->commitOrRelease();
      }
      catch (DatabaseException) {
        $savepoint->rollBack();
      }
    }
    $this->binVerified = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    $this->verifyBinInTransaction();
    return parent::getMultiple($cids, $allow_invalid);
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $items): void {
    $this->verifyBinInTransaction();
    parent::setMultiple($items);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids): void {
    $this->verifyBinInTransaction();
    parent::deleteMultiple($cids);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll(): void {
    $this->verifyBinInTransaction();
    parent::deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMultiple(array $cids): void {
    $this->verifyBinInTransaction();
    parent::invalidateMultiple($cids);
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection(): void {
    $this->verifyBinInTransaction();
    parent::garbageCollection();
  }

  /**
   * {@inheritdoc}
   */
  public function removeBin(): void {
    $this->binVerified = FALSE;
    if ($this->connection->inTransaction() && !$this->connection->schema()->tableExists($this->bin)) {
      // Nothing to drop, and a failing drop would abort the transaction.
      return;
    }
    parent::removeBin();
  }

}
