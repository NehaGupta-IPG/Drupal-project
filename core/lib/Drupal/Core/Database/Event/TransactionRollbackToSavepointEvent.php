<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Event;

/**
 * Represents the rollback of a transaction to a savepoint.
 */
final class TransactionRollbackToSavepointEvent extends TransactionEventBase {
}
