<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database\Stub;

use Drupal\Core\Database\Transaction\TransactionManagerBase;

/**
 * A stub of the TransactionManagerBase class for testing purposes.
 */
class StubTransactionManager extends TransactionManagerBase {

  /**
   * Should methods succeed or not.
   */
  public bool $success = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function beginClientTransaction(): bool {
    return $this->success;
  }

  /**
   * {@inheritdoc}
   */
  protected function addClientSavepoint(string $name): bool {
    return $this->success;
  }

  /**
   * {@inheritdoc}
   */
  protected function rollbackClientSavepoint(string $name): bool {
    return $this->success;
  }

  /**
   * {@inheritdoc}
   */
  protected function releaseClientSavepoint(string $name): bool {
    return $this->success;
  }

  /**
   * {@inheritdoc}
   */
  protected function commitClientTransaction(): bool {
    return $this->success;
  }

  /**
   * {@inheritdoc}
   */
  protected function rollbackClientTransaction(): bool {
    return $this->success;
  }

}
