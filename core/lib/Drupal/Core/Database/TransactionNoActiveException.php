<?php

namespace Drupal\Core\Database;

@trigger_error('Drupal\Core\Database\TransactionNoActiveException is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. There is no replacement. See https://www.drupal.org/node/7654123', E_USER_DEPRECATED);

/**
 * Exception for when popTransaction() is called with no active transaction.
 *
 * This exception will be thrown when the client connection commit() is called.
 * Code should never call this method directly.
 *
 * @deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. There is no
 *    replacement.
 *
 * @see https://www.drupal.org/node/7654123
 */
class TransactionNoActiveException extends TransactionException {}
