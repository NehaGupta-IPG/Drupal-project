<?php

namespace Drupal\Core\Database;

/**
 * Exception for when popTransaction() is called with no active transaction.
 *
 * This exception will be thrown when the client connection commit() is called.
 * Code should never call this method directly.
 */
class TransactionNoActiveException extends TransactionException {}
