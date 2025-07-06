<?php

declare(strict_types=1);

namespace Drupal\sqlite3\Driver\Database\sqlite3;

use Drupal\Core\Database\Transaction\ClientConnectionTransactionState;
use Drupal\Core\Database\Transaction\TransactionManagerBase;

/**
 * SQLite implementation of TransactionManagerInterface.
 */
class TransactionManager extends TransactionManagerBase {

  /**
   * {@inheritdoc}
   */
  protected function beginClientTransaction(): bool {
    return $this->connection->getClientConnection()->exec('BEGIN IMMEDIATE TRANSACTION');
  }

  /**
   * {@inheritdoc}
   */
  protected function rollbackClientTransaction(): bool {
    $clientRollback = $this->connection->getClientConnection()->exec('ROLLBACK');
    $this->setConnectionTransactionState($clientRollback ?
      ClientConnectionTransactionState::RolledBack :
      ClientConnectionTransactionState::RollbackFailed
    );
    return $clientRollback;
  }

  /**
   * {@inheritdoc}
   */
  protected function commitClientTransaction(): bool {
    $clientCommit = $this->connection->getClientConnection()->exec('COMMIT');
    $this->setConnectionTransactionState($clientCommit ?
      ClientConnectionTransactionState::Committed :
      ClientConnectionTransactionState::CommitFailed
    );
    return $clientCommit;
  }

}
