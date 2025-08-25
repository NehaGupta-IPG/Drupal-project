<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Event;

/**
 * Enumeration of the transaction related database events.
 */
enum TransactionEvent: string {

  case Begin = TransactionBeginEvent::class;
  case Commit = TransactionCommitEvent::class;
  case CommitFailure = TransactionCommitFailureEvent::class;
  case ReleaseSavepoint = TransactionReleaseSavepointEvent::class;
  case ReleaseSavepointFailure = TransactionReleaseSavepointFailureEvent::class;
  case Rollback = TransactionRollbackEvent::class;
  case RollbackFailure = TransactionRollbackFailureEvent::class;
  case RollbackToSavepoint = TransactionRollbackToSavepointEvent::class;
  case RollbackToSavepointFailure = TransactionRollbackToSavepointFailureEvent::class;
  case Savepoint = TransactionSavepointEvent::class;

  case AutoCommit = TransactionAutoCommitEvent::class;

  /**
   * Returns an array with all transaction related event classes.
   *
   * @return list<class-string<\Drupal\Core\Database\Event\TransactionEventBase>>
   *   An array with all transaction related event classes.
   */
  public static function all(): array {
    return array_map(fn(self $case) => $case->value, self::cases());
  }

}
