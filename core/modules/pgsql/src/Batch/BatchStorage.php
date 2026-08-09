<?php

declare(strict_types=1);

namespace Drupal\pgsql\Batch;

use Drupal\Core\Batch\BatchStorage as CoreBatchStorage;
use Drupal\Core\Database\DatabaseException;

/**
 * PostgreSQL implementation of the batch storage service.
 *
 * The batch storage recovers from statement failures caused by a missing
 * batch table by creating the table. On PostgreSQL a failed statement aborts
 * the whole enclosing transaction, so inside a transaction this recovery
 * would leave the transaction unusable. Instead of running every statement
 * with a savepoint, this implementation makes sure the batch table exists
 * before it is used inside a transaction. Outside a transaction a failed
 * statement has no lasting effect and the recovery of the parent class works
 * unchanged.
 */
class BatchStorage extends CoreBatchStorage {

  /**
   * Whether the batch table is known to exist.
   */
  protected bool $tableVerified = FALSE;

  /**
   * Makes sure the batch table exists before it is used in a transaction.
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
        $this->connection->schema()->createSchemaFromDefinition($this->schemaDefinition());
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
  public function load($id) {
    $this->verifyTableInTransaction();
    return parent::load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($id) {
    $this->verifyTableInTransaction();
    parent::delete($id);
  }

  /**
   * {@inheritdoc}
   */
  public function update(array $batch) {
    $this->verifyTableInTransaction();
    parent::update($batch);
  }

  /**
   * {@inheritdoc}
   */
  public function cleanup() {
    $this->verifyTableInTransaction();
    parent::cleanup();
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): int {
    $this->verifyTableInTransaction();
    return parent::getId();
  }

}
