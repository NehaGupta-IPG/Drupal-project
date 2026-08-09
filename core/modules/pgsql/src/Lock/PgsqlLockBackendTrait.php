<?php

declare(strict_types=1);

namespace Drupal\pgsql\Lock;

use Drupal\Core\Database\DatabaseException;

/**
 * PostgreSQL specific behavior for the database lock backends.
 *
 * ::acquire() uses a single upsert query instead of the separate update and
 * insert queries of the parent class. INSERT ... ON CONFLICT never fails
 * when two requests insert the row for the same lock at the same time: the
 * losing insert turns into a conflict update. No statement failure recovery
 * is needed for the race.
 *
 * On PostgreSQL a failed statement aborts the whole enclosing transaction.
 * The trait therefore makes sure the semaphore table exists before it is
 * used, so a missing table cannot fail a statement.
 *
 * ::releaseAll() is not covered by the pre-flight check. It runs at request
 * shutdown for every request, and it only executes a statement when a lock
 * was acquired, which verified the table already.
 */
trait PgsqlLockBackendTrait {

  /**
   * Whether the semaphore table is known to exist.
   */
  protected bool $tableVerified = FALSE;

  /**
   * Makes sure the semaphore table exists before it is used.
   */
  protected function verifyTable(): void {
    if ($this->tableVerified) {
      return;
    }
    if (!$this->database->schema()->tableExists(static::TABLE_NAME)) {
      // Another process can create the table at the same time. The savepoint
      // keeps an enclosing transaction usable when that race is lost.
      $savepoint = $this->database->startTransaction();
      try {
        $this->database->schema()->createTable(static::TABLE_NAME, $this->schemaDefinition());
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
   *
   * Unlike the parent implementation, a lock that this request held before
   * it was broken is reacquired when the lock is free again.
   */
  public function acquire($name, $timeout = 30.0) {
    $this->verifyTable();
    $name = $this->normalizeName($name);

    // Insure that the timeout is at least 1 ms.
    $timeout = max($timeout, 0.001);
    $now = microtime(TRUE);

    // A single upsert query covers every way to get the lock: it extends a
    // lock this request already holds, takes over an expired lock, and
    // inserts the row when the lock is free. When another request holds the
    // lock the WHERE clause stops the conflict update, no row is returned
    // and the lock is not acquired.
    $query = 'INSERT INTO {semaphore} AS [t] ([name], [value], [expire]) VALUES (:name, :value, :expire) ';
    $query .= 'ON CONFLICT ([name]) DO UPDATE SET [value] = EXCLUDED.[value], [expire] = EXCLUDED.[expire] ';
    $query .= 'WHERE [t].[value] = EXCLUDED.[value] OR [t].[expire] < :now RETURNING 1';
    $acquired = (bool) $this->database->query($query, [
      ':name' => $name,
      ':value' => $this->getLockId(),
      ':expire' => $now + $timeout,
      ':now' => $now,
    ])->fetchField();

    if ($acquired) {
      // We track all acquired locks in the global variable.
      $this->locks[$name] = TRUE;
    }
    else {
      // Another request holds the lock. When this request was extending a
      // lock it already held, that lock was broken.
      unset($this->locks[$name]);
    }
    return $acquired;
  }

  /**
   * {@inheritdoc}
   */
  public function lockMayBeAvailable($name) {
    $this->verifyTable();
    return parent::lockMayBeAvailable($name);
  }

  /**
   * {@inheritdoc}
   */
  public function release($name): void {
    $this->verifyTable();
    parent::release($name);
  }

}
