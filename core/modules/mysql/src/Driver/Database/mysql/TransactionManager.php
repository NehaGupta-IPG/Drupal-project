<?php

declare(strict_types=1);

namespace Drupal\mysql\Driver\Database\mysql;

use Drupal\Core\Database\Transaction\TransactionManagerBase;
use Drupal\Core\Database\TransactionNoActiveException;

/**
 * MySql implementation of TransactionManagerInterface.
 *
 * MySQL will automatically commit transactions when tables are altered or
 * created (DDL transactions are not supported). However, pdo_mysql tracks
 * whether a client connection is still active and we can prevent triggering
 * exceptions.
 */
class TransactionManager extends TransactionManagerBase {

  /**
   * {@inheritdoc}
   */
  protected function beginClientTransaction(): bool {
    return $this->connection->getClientConnection()->beginTransaction();
  }

  /**
   * {@inheritdoc}
   */
  protected function rollbackClientSavepoint(string $name): bool {
    if (!$this->connection->getClientConnection()->inTransaction()) {
      throw new TransactionNoActiveException();
    }
    return parent::rollbackClientSavepoint($name);
  }

  /**
   * {@inheritdoc}
   */
  protected function releaseClientSavepoint(string $name): bool {
    if (!$this->connection->getClientConnection()->inTransaction()) {
      throw new TransactionNoActiveException();
    }
    return parent::releaseClientSavepoint($name);
  }

  /**
   * {@inheritdoc}
   */
  protected function commitClientTransaction(): bool {
    return $this->connection->getClientConnection()->commit();
  }

  /**
   * {@inheritdoc}
   */
  protected function rollbackClientTransaction(): bool {
    if (!$this->connection->getClientConnection()->inTransaction()) {
      throw new TransactionNoActiveException();
    }
    return $this->connection->getClientConnection()->rollBack();
  }

}
