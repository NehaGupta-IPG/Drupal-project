<?php

declare(strict_types=1);

namespace Drupal\pgsql\Lock;

use Drupal\Core\Database\Connection;
use Drupal\pgsql\TableVerifyTrait;

/**
 * PostgreSQL specific behavior for the database lock backends.
 *
 * ::acquire() uses an upsert query instead of the insert query of the parent
 * class. INSERT ... ON CONFLICT never fails when two requests insert the row
 * for the same lock at the same time: the losing insert turns into a
 * conflict update. No statement failure recovery is needed for the race.
 *
 * On PostgreSQL a failed statement aborts the whole enclosing transaction.
 * The trait therefore makes sure the semaphore table exists before it is
 * used, so a missing table cannot fail a statement.
 *
 * ::releaseAll() is not covered by the pre-flight check. It runs at request
 * shutdown for every request, and it only executes a statement when a lock
 * was acquired, which verified the table already.
 *
 * @see \Drupal\pgsql\TableVerifyTrait
 */
trait PgsqlLockBackendTrait {

  use TableVerifyTrait;

  /**
   * {@inheritdoc}
   *
   * The lock backends keep their connection in $this->database.
   */
  protected function verifyTableConnection(): Connection {
    return $this->database;
  }

  /**
   * {@inheritdoc}
   */
  public function acquire($name, $timeout = 30.0) {
    $this->verifyTable(static::TABLE_NAME);
    $name = $this->normalizeName($name);

    // Insure that the timeout is at least 1 ms.
    $timeout = max($timeout, 0.001);
    $now = microtime(TRUE);

    if (isset($this->locks[$name])) {
      // Try to extend the expiration of a lock we already acquired. An update
      // query cannot fail a statement, so it needs no recovery. The lock ID
      // condition cannot be moved into the upsert below: the persistent lock
      // backend shares one lock ID between all requests, so a matching lock
      // ID does not prove that this request holds the lock.
      $success = (bool) $this->database->update('semaphore')
        ->fields(['expire' => $now + $timeout])
        ->condition('name', $name)
        ->condition('value', $this->getLockId())
        ->execute();
      if (!$success) {
        // The lock was broken.
        unset($this->locks[$name]);
      }
      return $success;
    }

    // A single upsert query inserts the row when the lock is free and takes
    // over an expired lock. When another request holds the lock the WHERE
    // clause stops the conflict update, no row is returned and the lock is
    // not acquired.
    $query = 'INSERT INTO {semaphore} AS [t] ([name], [value], [expire]) VALUES (:name, :value, :expire) ';
    $query .= 'ON CONFLICT ([name]) DO UPDATE SET [value] = EXCLUDED.[value], [expire] = EXCLUDED.[expire] ';
    $query .= 'WHERE [t].[expire] < :now RETURNING 1';
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
    return $acquired;
  }

  /**
   * {@inheritdoc}
   */
  public function lockMayBeAvailable($name) {
    $this->verifyTable(static::TABLE_NAME);
    return parent::lockMayBeAvailable($name);
  }

  /**
   * {@inheritdoc}
   */
  public function release($name): void {
    $this->verifyTable(static::TABLE_NAME);
    parent::release($name);
  }

}
