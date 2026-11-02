<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Event;

/**
 * Represents the failed rollback of a transaction.
 */
final class TransactionRollbackFailureEvent extends TransactionFailureEventBase {
}
