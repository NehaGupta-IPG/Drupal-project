<?php

namespace Drupal\pgsql\Driver\Database\pgsql;

use Drupal\Core\Database\Query\Truncate as QueryTruncate;

// cspell:ignore MVCC

/**
 * PostgreSQL implementation of \Drupal\Core\Database\Query\Truncate.
 *
 * Honors the "reset_serial_id" option by adding RESTART IDENTITY to the
 * statement. TRUNCATE leaves the sequences owned by the columns of the table
 * alone without it.
 */
class Truncate extends QueryTruncate {

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if ($this->connection->inTransaction()) {
      $savepoint = $this->connection->startTransaction('mimic_implicit_commit');
    }
    try {
      parent::execute();
    }
    catch (\Exception $e) {
      if (isset($savepoint)) {
        $savepoint->rollback();
      }
      throw $e;
    }
    if (isset($savepoint)) {
      $savepoint->commitOrRelease();
    }
  }

  /**
   * {@inheritdoc}
   *
   * On PostgreSQL, TRUNCATE is fully transactional: it can be rolled back
   * and does not cause an implicit commit, so there is no need to fall back
   * to the slower DELETE query when inside a transaction. TRUNCATE is much
   * faster on large tables and, unlike DELETE, does not leave dead rows
   * behind for VACUUM to clean up.
   *
   * Note that TRUNCATE takes an ACCESS EXCLUSIVE lock on the table that is
   * held until the transaction ends, and it is not MVCC-safe with respect to
   * concurrent transactions using a snapshot taken before the truncation.
   *
   * @see https://www.postgresql.org/docs/current/sql-truncate.html
   */
  public function __toString() {
    // Create a sanitized comment string to prepend to the query.
    $comments = $this->connection->makeComment($this->comments);

    // RESTART IDENTITY rewinds the sequences owned by the columns of the table
    // to their start value, in the same statement that empties the table.
    $restart_identity = empty($this->queryOptions['reset_serial_id']) ? '' : 'RESTART IDENTITY ';

    return $comments . 'TRUNCATE {' . $this->connection->escapeTable($this->table) . '} ' . $restart_identity;
  }

}
