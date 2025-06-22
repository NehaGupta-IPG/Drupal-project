<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Event;

/**
 * Base class for database transaction events.
 */
abstract class TransactionEventBase extends DatabaseEvent {

  /**
   * Constructs a TransactionEventBase object.
   *
   * See 'Customizing database settings' in settings.php for an explanation of
   * the $key and $target connection values.
   *
   * @param string $key
   *   The database connection key.
   * @param string $target
   *   The database connection target.
   * @param string $id
   *   The id of the transaction.
   * @param string $name
   *   The name of the transaction.
   * @param array $stackItems
   *   The current transaction stack items.
   */
  public function __construct(
    public readonly string $key,
    public readonly string $target,
    public readonly string $id,
    public readonly string $name,
    public readonly array $stackItems,
  ) {
    parent::__construct();
  }

}
