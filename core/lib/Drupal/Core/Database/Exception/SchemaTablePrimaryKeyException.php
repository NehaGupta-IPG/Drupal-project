<?php

namespace Drupal\Core\Database\Exception;

use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Database\SchemaException;

/**
 * Exception thrown for primary key related errors.
 */
class SchemaTablePrimaryKeyException extends SchemaException implements DatabaseException {
}
