<?php

namespace Drupal\Core\Database\Exception;

use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Database\SchemaException;

/**
 * Exception thrown when a database schema failed to be created.
 */
class SchemaCreationFailureException extends SchemaException implements DatabaseException {
}
