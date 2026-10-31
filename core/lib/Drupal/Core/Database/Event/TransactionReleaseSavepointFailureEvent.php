<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Event;

/**
 * Represents a failed savepoint release.
 */
final class TransactionReleaseSavepointFailureEvent extends TransactionCommitOrReleaseFailureEventBase {
}
