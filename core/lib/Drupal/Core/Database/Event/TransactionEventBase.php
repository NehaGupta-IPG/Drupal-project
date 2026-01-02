<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Event;

use Drupal\Core\Database\Transaction\ClientConnectionTransactionState;

/**
 * Base class for database transaction events.
 */
abstract class TransactionEventBase extends DatabaseEvent {

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
   */
  public function __construct(
    public readonly string $key,
    public readonly string $target,
    public readonly string $id,
    public readonly string $name,
    public readonly ClientConnectionTransactionState $transactionState,
    public readonly array $stackItems,
  ) {
    parent::__construct();
  }

}
