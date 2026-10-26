<?php

declare(strict_types=1);

namespace Drupal\Core\Session;

use Drupal\Core\Database\Connection;

/**
 * Provides the default user session repository.
 */
readonly class UserSessionRepository implements UserSessionRepositoryInterface {

  public function __construct(protected Connection $connection) {
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll(int $uid): void {
    // A driver that does not create tables lazily aborts the enclosing
    // transaction with the failed delete query below. When the table does not
    // exist there are no sessions to delete. The result is not memoized
    // because this class is read-only, and deleting the sessions of a user
    // inside a transaction is rare enough that the extra lookup does not
    // matter.
    if (!$this->connection->supportsLazyTableCreation()
      && $this->connection->inTransaction()
      && !$this->connection->schema()->tableExists('sessions')) {
      return;
    }
    try {
      // Delete session data.
      $this->connection->delete('sessions')
        ->condition('uid', $uid)
        ->execute();
    }
    // Swallow the error if the table hasn't been created yet.
    catch (\Exception) {
    }
  }

}
