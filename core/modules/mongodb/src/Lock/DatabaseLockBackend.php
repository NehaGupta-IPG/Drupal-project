<?php

namespace Drupal\mongodb\Lock;

use Drupal\Core\Lock\DatabaseLockBackend as CoreDatabaseLockBackend;
use Drupal\mongodb\Driver\Database\mongodb\Statement;
use MongoDB\Driver\Exception\BulkWriteException;

/**
 * The MongoDB implementation of \Drupal\Core\Lock\DatabaseLockBackend.
 */
class DatabaseLockBackend extends CoreDatabaseLockBackend {

  /**
   * Indicator for the existence of the database table.
   *
   * @var bool
   */
  protected $tableExists = FALSE;

  /**
   * {@inheritdoc}
   */
  public function acquire($name, $timeout = 30.0) {
    // For MongoDB the table need to exists. Otherwise MongoDB creates one
    // without the correct validation.
    if (!$this->tableExists) {
      $this->tableExists = $this->ensureTableExists();
    }

    $name = $this->normalizeName($name);

    // Insure that the timeout is at least 1 ms.
    $timeout = max($timeout, 0.001);
    $expire = microtime(TRUE) + $timeout;
    if (isset($this->locks[$name])) {
      try {
        // Try to extend the expiration of a lock we already acquired.
        $success = (bool) $this->database->update('semaphore')
          ->fields(['expire' => $expire])
          ->condition('name', $name)
          ->condition('value', $this->getLockId())
          ->execute();
      }
      catch (BulkWriteException $e) {
        $success = FALSE;
      }
      if (!$success) {
        // The lock was broken.
        unset($this->locks[$name]);
      }
      return $success;
    }
    else {
      // Optimistically try to acquire the lock, then retry once if it fails.
      // The first time through the loop cannot be a retry.
      $retry = FALSE;
      // We always want to do this code at least once.
      do {
        try {
          $this->database->insert('semaphore')
            ->fields([
              'name' => $name,
              'value' => $this->getLockId(),
              'expire' => $expire,
            ])
            ->execute();
          // We track all acquired locks in the global variable.
          $this->locks[$name] = TRUE;
          // We never need to try again.
          $retry = FALSE;
        }
        catch (\Exception $e) {
          // Create the semaphore table if it does not exist and retry.
          if ($this->ensureTableExists()) {
            // Retry only once.
            $retry = !$retry;
          }
          elseif ($e instanceof BulkWriteException) {
            $retry = $retry ? FALSE : $this->lockMayBeAvailable($name);
          }
          else {
            throw $e;
          }
        }
        // We only retry in case the first attempt failed, but we then broke
        // an expired lock.
      } while ($retry);
    }

    return isset($this->locks[$name]);
  }

  /**
   * {@inheritdoc}
   */
  public function lockMayBeAvailable($name) {
    $name = $this->normalizeName($name);

    try {
      $prefixed_table = $this->database->getPrefix() . 'semaphore';
      $cursor = $this->database->getConnection()->selectCollection($prefixed_table)->find(
        ['name' => ['$eq' => $name]],
        [
          'projection' => ['expire' => 1, 'value' => 1, '_id' => 0],
          'session' => $this->database->getMongodbSession(),
        ]
      );

      $statement = new Statement($this->database, $cursor, ['expire', 'value']);
      $lock = $statement->execute()->fetchAssoc();
    }
    catch (\Exception $e) {
      $this->catchException($e);
      // If the table does not exist yet then the lock may be available.
      $lock = FALSE;
    }
    if (!$lock) {
      return TRUE;
    }
    $expire = (float) $lock['expire'];
    $now = microtime(TRUE);
    if ($now > $expire) {
      // We check two conditions to prevent a race condition where another
      // request acquired the lock and set a new expire time. We add a small
      // number to $expire to avoid errors with float to string conversion.
      return (bool) $this->database->delete('semaphore')
        ->condition('name', $name)
        ->condition('value', $lock['value'])
        ->condition('expire', 0.0001 + $expire, '<=')
        ->execute();
    }
    return FALSE;
  }

}
