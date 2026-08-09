<?php

declare(strict_types=1);

namespace Drupal\pgsql\Queue;

use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Queue\DatabaseQueue as CoreDatabaseQueue;

/**
 * PostgreSQL implementation of the database queue.
 *
 * The database queue recovers from statement failures caused by a missing
 * queue table by creating the table and retrying. On PostgreSQL a failed
 * statement aborts the whole enclosing transaction, so inside a transaction
 * this recovery would leave the transaction unusable. Instead of running
 * every statement with a savepoint, this implementation makes sure the queue
 * table exists before it is used inside a transaction. Outside a transaction
 * a failed statement has no lasting effect and the recovery of the parent
 * class works unchanged.
 */
class DatabaseQueue extends CoreDatabaseQueue {

  /**
   * Whether the queue table is known to exist.
   */
  protected bool $tableVerified = FALSE;

  /**
   * Makes sure the queue table exists before it is used in a transaction.
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
  public function createItem($data) {
    $this->verifyTableInTransaction();
    return parent::createItem($data);
  }

  /**
   * {@inheritdoc}
   */
  public function numberOfItems() {
    $this->verifyTableInTransaction();
    return parent::numberOfItems();
  }

  /**
   * {@inheritdoc}
   */
  public function claimItem($lease_time = 30) {
    $this->verifyTableInTransaction();
    return parent::claimItem($lease_time);
  }

  /**
   * {@inheritdoc}
   */
  public function releaseItem($item) {
    $this->verifyTableInTransaction();
    return parent::releaseItem($item);
  }

  /**
   * {@inheritdoc}
   */
  public function delayItem($item, int $delay) {
    $this->verifyTableInTransaction();
    return parent::delayItem($item, $delay);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItem($item): void {
    $this->verifyTableInTransaction();
    parent::deleteItem($item);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteQueue(): void {
    $this->verifyTableInTransaction();
    parent::deleteQueue();
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection(): void {
    $this->verifyTableInTransaction();
    parent::garbageCollection();
  }

}
