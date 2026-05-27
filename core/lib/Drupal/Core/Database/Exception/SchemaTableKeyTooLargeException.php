<?php

namespace Drupal\Core\Database\Exception;

use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Database\SchemaException;

/**
 * Exception thrown if a key is too large.
 */
class SchemaTableKeyTooLargeException extends \Drupal\Core\Database\SchemaException implements \Drupal\Core\Database\DatabaseException {
}
