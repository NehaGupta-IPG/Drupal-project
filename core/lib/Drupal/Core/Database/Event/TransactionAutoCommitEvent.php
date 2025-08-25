<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Event;

/**
 * Emitted when an automatic commit is detected.
 *
 * In some cases the active transaction is automatically committed by the
 * database server when a DDL statement is executed during a transaction
 * (for example, MySql). In such cases we emit this event to notify that a
 * commit occurred while the Drupal transaction is still active.
 * A normal TransactionCommitEvent is anyway emitted later once the Drupal
 * transaction is committed.
 */
final class TransactionAutoCommitEvent extends TransactionEventBase {
}
