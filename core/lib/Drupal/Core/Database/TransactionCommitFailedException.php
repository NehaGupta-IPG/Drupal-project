<?php

namespace Drupal\Core\Database;

/**
 * Exception thrown when a commit() function fails.
 */
class TransactionCommitFailedException extends \Drupal\Core\Database\TransactionException implements \Drupal\Core\Database\DatabaseException {}
