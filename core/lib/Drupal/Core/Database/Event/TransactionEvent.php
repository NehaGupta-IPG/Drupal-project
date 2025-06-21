<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Event;

/**
 * Enumeration of the transaction related database events.
 */
enum TransactionEvent: string {

  case Begin = TransactionBeginEvent::class;
  case Savepoint = TransactionSavepointEvent::class;
  case Commit = TransactionCommitEvent::class;

  /**
   * Returns an array with all transaction related events.
   *
   * @return list<class-string<\Drupal\Core\Database\Event\TransactionEventBase>>
   *   An array with all transaction related events.
   */
  public static function all(): array {
    return array_map(fn(self $case) => $case->value, self::cases());
  }

}
