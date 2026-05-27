<?php

namespace Drupal\Core\Database\Exception;

/**
 * Exception thrown if the Primary Key must be dropped before an operation.
 */
class SchemaPrimaryKeyMustBeDroppedException extends \Drupal\Core\Database\SchemaException implements \Drupal\Core\Database\DatabaseException {
}
