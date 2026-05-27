<?php

namespace Drupal\Core\Database;

/**
 * Exception for when popTransaction() is called with no active transaction.
 */
class TransactionNoActiveException extends \Drupal\Core\Database\TransactionException implements \Drupal\Core\Database\DatabaseException {}
