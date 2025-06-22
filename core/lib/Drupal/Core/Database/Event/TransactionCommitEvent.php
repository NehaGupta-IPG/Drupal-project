<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Event;

/**
 * Represents the commit of a transaction.
 */
final class TransactionCommitEvent extends TransactionEventBase {

  /**
   * Constructs a TransactionCommitEvent object.
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
   * @param bool $autoCommit
   *   TRUE if the transaction auto-committed.
   * @param ?string $autoCommitReason
   *   The reason of the auto-commit.
   */
  public function __construct(
    string $key,
    string $target,
    string $id,
    string $name,
    array $stackItems,
    public readonly bool $autoCommit = FALSE,
    public readonly ?string $autoCommitReason = NULL,
  ) {
    parent::__construct($key, $target, $id, $name, $stackItems);
  }

}
