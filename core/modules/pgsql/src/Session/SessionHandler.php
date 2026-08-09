<?php

declare(strict_types=1);

namespace Drupal\pgsql\Session;

use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Session\SessionHandler as CoreSessionHandler;

/**
 * PostgreSQL implementation of the database session handler.
 *
 * The session handler recovers from statement failures caused by a missing
 * sessions table by creating the table and retrying, or by swallowing the
 * error. On PostgreSQL a failed statement aborts the whole enclosing
 * transaction, so inside a transaction this recovery would leave the
 * transaction unusable. Instead of running every statement with a savepoint,
 * this implementation makes sure the sessions table exists before it is used
 * inside a transaction. Outside a transaction a failed statement has no
 * lasting effect and the recovery of the parent class works unchanged.
 */
class SessionHandler extends CoreSessionHandler {

  /**
   * Whether the sessions table is known to exist.
   */
  protected bool $tableVerified = FALSE;

  /**
   * Makes sure the sessions table exists before it is used in a transaction.
   */
  protected function verifyTableInTransaction(): void {
    if ($this->tableVerified || !$this->connection->inTransaction()) {
      return;
    }
    if (!$this->connection->schema()->tableExists('sessions')) {
      // Another process can create the table at the same time. The savepoint
      // keeps the enclosing transaction usable when that race is lost.
      $savepoint = $this->connection->startTransaction();
      try {
        $this->connection->schema()->createTable('sessions', $this->schemaDefinition());
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
  public function read(#[\SensitiveParameter] string $sessionId): string {
    $this->verifyTableInTransaction();
    return parent::read($sessionId);
  }

  /**
   * {@inheritdoc}
   */
  public function write(#[\SensitiveParameter] string $sessionId, string $data): bool {
    $this->verifyTableInTransaction();
    return parent::write($sessionId, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function destroy(#[\SensitiveParameter] string $sessionId): bool {
    $this->verifyTableInTransaction();
    return parent::destroy($sessionId);
  }

  /**
   * {@inheritdoc}
   */
  public function gc(int $lifetime): int|false {
    $this->verifyTableInTransaction();
    return parent::gc($lifetime);
  }

}
