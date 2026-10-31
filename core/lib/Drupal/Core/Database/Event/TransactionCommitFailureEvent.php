<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Event;

/**
 * Represents the failed commit of a transaction.
 */
final class TransactionCommitFailureEvent extends TransactionCommitOrReleaseFailureEventBase {
}
