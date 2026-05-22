<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\DatabaseException;

/**
 * Exception for when the database connection object is not MongoDB\Database.
 */
class DatabaseNotMongodbConnectionException extends \Exception implements DatabaseException {
}
