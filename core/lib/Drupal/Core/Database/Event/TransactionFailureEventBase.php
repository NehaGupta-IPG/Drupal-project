<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Event;

use Drupal\Core\Database\Transaction\ClientConnectionTransactionState;

/**
 * Base class for database transaction failure events.
 */
abstract class TransactionFailureEventBase extends TransactionEventBase {

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
   * @param class-string $className
   *   The fully qualified class name of the exception.
   * @param string $message
   *   The message of the exception.
   */
  public function __construct(
    string $key,
    string $target,
    string $id,
    string $name,
    ClientConnectionTransactionState $transactionState,
    array $stackItems,
    public readonly string $className,
    public readonly string $message,
  ) {
    parent::__construct($key, $target, $id, $name, $transactionState, $stackItems);
  }

}
