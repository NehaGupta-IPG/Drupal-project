<?php

namespace Drupal\Core\Database\Query;

/**
 * Exception thrown if an upsert query doesn't specify a unique field.
 */
class NoUniqueFieldException extends \InvalidArgumentException implements \Drupal\Core\Database\DatabaseException {}
