<?php

declare(strict_types=1);

namespace Drupal\pgsql\Cache;

use Drupal\Core\Cache\DatabaseCacheTagsChecksum as CoreDatabaseCacheTagsChecksum;
use Drupal\Core\Database\DatabaseException;

/**
 * PostgreSQL implementation of the database cache tags checksum service.
 *
 * This service recovers from statement failures caused by a missing
 * cachetags table by creating the table. On PostgreSQL a failed statement
 * aborts the whole enclosing transaction, so inside a transaction this
 * recovery would leave the transaction unusable. Instead of running every
 * statement with a savepoint, this implementation makes sure the cachetags
 * table exists before it is used inside a transaction. Outside a transaction
 * a failed statement has no lasting effect and the recovery of the parent
 * class works unchanged.
 */
class DatabaseCacheTagsChecksum extends CoreDatabaseCacheTagsChecksum {

  /**
   * Whether the cachetags table is known to exist.
   */
  protected bool $tableVerified = FALSE;

  /**
   * Makes sure the cachetags table exists before it is used in a transaction.
   */
  protected function verifyTableInTransaction(): void {
    if ($this->tableVerified || !$this->connection->inTransaction()) {
      return;
    }
    $schema = $this->connection->schema();
    if (!$schema->tableExists('cachetags')) {
      // Another process can create the table at the same time. The savepoint
      // keeps the enclosing transaction usable when that race is lost.
      $savepoint = $this->connection->startTransaction();
      try {
        $schema->createTable('cachetags', $this->schemaDefinition());
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
  protected function doInvalidateTags(array $tags): void {
    $this->verifyTableInTransaction();
    parent::doInvalidateTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  protected function getTagInvalidationCounts(array $tags) {
    $this->verifyTableInTransaction();
    return parent::getTagInvalidationCounts($tags);
  }

  /**
   * {@inheritdoc}
   */
  public function purge(): void {
    $this->verifyTableInTransaction();
    parent::purge();
  }

}
