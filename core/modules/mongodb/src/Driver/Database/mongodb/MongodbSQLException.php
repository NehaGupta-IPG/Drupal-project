<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\DatabaseException;

/**
 * Exception thrown if a query containing SQL is run against a MongoDB database.
 */
class MongodbSQLException extends \Exception implements DatabaseException {}
