<?php

namespace Drupal\Core\Database;

/**
 * Exception thrown when a savepoint or transaction name occurs twice.
 */
class TransactionNameNonUniqueException extends \Drupal\Core\Database\TransactionException implements \Drupal\Core\Database\DatabaseException {}
