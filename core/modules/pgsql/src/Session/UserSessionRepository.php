<?php

declare(strict_types=1);

namespace Drupal\pgsql\Session;

use Drupal\Core\Session\UserSessionRepository as CoreUserSessionRepository;

/**
 * PostgreSQL implementation of the user session repository.
 *
 * The user session repository swallows a statement failure caused by a
 * missing sessions table. On PostgreSQL a failed statement aborts the whole
 * enclosing transaction, so inside a transaction this would leave the
 * transaction unusable. Instead of running the statement with a savepoint,
 * this implementation checks for the sessions table first; when it does not
 * exist there are no sessions to delete. Outside a transaction a failed
 * statement has no lasting effect and the recovery of the parent class works
 * unchanged.
 *
 * The check is not memoized because the parent class is read-only; deleting
 * the sessions of a user inside a transaction is rare enough that the extra
 * lookup does not matter.
 */
readonly class UserSessionRepository extends CoreUserSessionRepository {

  /**
   * {@inheritdoc}
   */
  public function deleteAll(int $uid): void {
    if ($this->connection->inTransaction() && !$this->connection->schema()->tableExists('sessions')) {
      return;
    }
    parent::deleteAll($uid);
  }

}
