<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Event;

use Drupal\Core\Database\Transaction\ClientConnectionTransactionState;
use Drupal\Core\Database\Transaction\CommitType;

/**
 * Represents the commit of a transaction.
 */
abstract class TransactionCommitOrReleaseEventBase extends TransactionEventBase {

  /**
   * Constructor.
   *
   * See 'Customizing database settings' in settings.php for an explanation of
   * the $key and $target connection values.
   *
   * @param string $key
   *   The database connection key.
   * @param string $target
   *   The database connection target.
   * @param string $id
   *   The ID of the transaction.
   * @param string $name
   *   The name of the transaction.
   * @param \Drupal\Core\Database\Transaction\ClientConnectionTransactionState $transactionState
   *   The state of the client transaction.
   * @param array $stackItems
   *   The current transaction stack items.
   * @param \Drupal\Core\Database\Transaction\CommitType $type
   *   The type of commit.
   */
  public function __construct(
    string $key,
    string $target,
    string $id,
    string $name,
    ClientConnectionTransactionState $transactionState,
    array $stackItems,
    public readonly CommitType $type,
  ) {
    parent::__construct($key, $target, $id, $name, $transactionState, $stackItems);
  }

}
