<?php

declare(strict_types=1);

namespace Drupal\pgsql\Routing;

use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Routing\MatcherDumper as CoreMatcherDumper;

/**
 * PostgreSQL implementation of the database matcher dumper.
 *
 * The matcher dumper recovers from a delete failure caused by a missing
 * router table by creating the table and retrying. On PostgreSQL a failed
 * statement aborts the whole enclosing transaction, so this recovery would
 * leave the transaction unusable. Because dump() starts its own transaction,
 * this implementation makes sure the router table exists before every dump,
 * not only when a caller already started a transaction.
 */
class MatcherDumper extends CoreMatcherDumper {

  /**
   * Whether the router table is known to exist.
   */
  protected bool $tableVerified = FALSE;

  /**
   * Makes sure the router table exists before it is dumped to.
   */
  protected function verifyTable(): void {
    if ($this->tableVerified) {
      return;
    }
    if (!$this->connection->schema()->tableExists($this->tableName)) {
      // Another process can create the table at the same time, so a failed
      // creation is ignored. Inside an enclosing transaction the savepoint
      // keeps the transaction usable when that race is lost.
      $savepoint = $this->connection->inTransaction() ? $this->connection->startTransaction() : NULL;
      try {
        $this->connection->schema()->createTable($this->tableName, $this->schemaDefinition());
        $savepoint?->commitOrRelease();
      }
      catch (DatabaseException) {
        $savepoint?->rollBack();
      }
    }
    $this->tableVerified = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function dump(array $options = []): string {
    $this->verifyTable();
    return parent::dump($options);
  }

}
