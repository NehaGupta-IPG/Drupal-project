<?php

declare(strict_types=1);

namespace Drupal\pgsql;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseException;

/**
 * Makes sure a table exists before it is used.
 *
 * Core services that own their table recover from statement failures caused
 * by a missing table by creating the table and retrying. On PostgreSQL a
 * failed statement aborts the whole enclosing transaction, so inside a
 * transaction this recovery would leave the transaction unusable. Instead of
 * running every statement with a savepoint, the PostgreSQL implementations of
 * those services make sure the table exists before it is used. Outside a
 * transaction a failed statement has no lasting effect and the recovery of
 * the parent class works unchanged.
 *
 * The trait carries the pre-flight check that those implementations share,
 * because each of them extends its own core class and cannot share the code
 * through inheritance.
 */
trait TableVerifyTrait {

  /**
   * The tables that are known to exist, keyed by table name.
   *
   * @var array<string, true>
   */
  protected array $verifiedTables = [];

  /**
   * Makes sure a table exists before it is used inside a transaction.
   *
   * @param string $table
   *   The name of the table.
   */
  protected function verifyTableInTransaction(string $table): void {
    if (isset($this->verifiedTables[$table]) || !$this->verifyTableConnection()->inTransaction()) {
      return;
    }
    $this->verifyTable($table);
  }

  /**
   * Makes sure a table exists before it is used.
   *
   * @param string $table
   *   The name of the table.
   */
  protected function verifyTable(string $table): void {
    if (isset($this->verifiedTables[$table])) {
      return;
    }
    // @todo Use the to be created table information service that will be
    // prewarmed to check if a table exists. A site in production should
    // therefore never reach the hard table exist round trip to the database.
    // See: https://www.drupal.org/node/3615649
    $connection = $this->verifyTableConnection();
    if (!$connection->schema()->tableExists($table)) {
      // Another process can create the table at the same time, so a failed
      // creation is ignored. Inside a transaction the savepoint keeps the
      // enclosing transaction usable when that race is lost.
      $savepoint = $connection->inTransaction() ? $connection->startTransaction() : NULL;
      try {
        $this->createVerifiedTable($table);
        $savepoint?->commitOrRelease();
      }
      catch (DatabaseException) {
        $savepoint?->rollBack();
      }
    }
    $this->verifiedTables[$table] = TRUE;
  }

  /**
   * Returns the connection the table lives on.
   *
   * @return \Drupal\Core\Database\Connection
   *   The database connection.
   */
  protected function verifyTableConnection(): Connection {
    return $this->connection;
  }

  /**
   * Creates a table that does not exist yet.
   *
   * @param string $table
   *   The name of the table.
   */
  protected function createVerifiedTable(string $table): void {
    $this->verifyTableConnection()->schema()->createTable($table, $this->schemaDefinition());
  }

}
