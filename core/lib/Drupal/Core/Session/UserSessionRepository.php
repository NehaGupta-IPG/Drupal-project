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
    // On some drivers, a failed delete query below aborts the enclosing
    // transaction. When the table does not exist there are no sessions to
    // delete. Deleting the sessions of a user inside a transaction is rare
    // enough that the extra lookup does not matter.
    if ($this->connection->inTransaction() && !$this->connection->schema()->tableExists('sessions')) {
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
