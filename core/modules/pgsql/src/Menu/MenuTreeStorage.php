<?php

declare(strict_types=1);

namespace Drupal\pgsql\Menu;

use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Menu\MenuTreeStorage as CoreMenuTreeStorage;

/**
 * PostgreSQL implementation of the menu tree storage.
 *
 * The menu tree storage recovers from select failures caused by a missing
 * menu tree table by creating the table and retrying. On PostgreSQL a failed
 * statement aborts the whole enclosing transaction, so inside a transaction
 * this recovery would leave the transaction unusable. Instead of running
 * every select with a savepoint, this implementation makes sure the table
 * exists before it is used inside a transaction. Outside a transaction a
 * failed statement has no lasting effect and the recovery of the parent
 * class works unchanged.
 */
class MenuTreeStorage extends CoreMenuTreeStorage {

  /**
   * Whether the menu tree table is known to exist.
   */
  protected bool $tableVerified = FALSE;

  /**
   * Makes sure the menu tree table exists before use in a transaction.
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
  protected function safeExecuteSelect(SelectInterface $query) {
    $this->verifyTableInTransaction();
    return parent::safeExecuteSelect($query);
  }

}
