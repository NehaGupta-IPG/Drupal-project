<?php

declare(strict_types=1);

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\Transaction;
use Drupal\Core\Database\Transaction\ClientConnectionTransactionState;
use Drupal\Core\Database\Transaction\TransactionManagerBase;

/**
 * MongoDB implementation of TransactionManagerInterface.
 */
class TransactionManager extends TransactionManagerBase {

  /**
   * Destructor.
   *
   * When destructing, $stack must have been already emptied.
   */
  public function __destruct() {
    // @todo Fix working with Drupal transactions.
    // assert($this->stack === [], "Transaction \$stack was not empty. Active
    // stack: " . $this->dumpStackItemsAsString());
  }

  /**
   * {@inheritdoc}
   */
  public function push(string $name = ''): Transaction {
    if ($this->stackDepth() > 0) {
      throw new \LogicException('The transaction has already been started. MongoDB does not support nested transactions.');
    }

    return parent::push($name);
  }

  /**
   * {@inheritdoc}
   */
  protected function beginClientTransaction(): bool {
    $this->connection->getMongodbSession()->startTransaction();

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function inTransaction(): bool {
    return (bool) $this->connection->getMongodbSession() && $this->connection->getMongodbSession()->isInTransaction() && parent::inTransaction();
  }

  /**
   * {@inheritdoc}
   */
  protected function addClientSavepoint(string $name): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function rollbackClientSavepoint(string $name): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function releaseClientSavepoint(string $name): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function rollbackClientTransaction(): bool {
    try {
      $this->connection->getMongodbSession()->abortTransaction();
      $clientRollback = TRUE;
    }
    catch (\Exception) {
      $clientRollback = FALSE;
    }

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
    try {
      $this->connection->getMongodbSession()->commitTransaction();
      $clientCommit = TRUE;
    }
    catch (\Exception) {
      $clientCommit = FALSE;
    }

    $this->setConnectionTransactionState($clientCommit ?
      ClientConnectionTransactionState::Committed :
      ClientConnectionTransactionState::CommitFailed
    );

    return $clientCommit;
  }

}
