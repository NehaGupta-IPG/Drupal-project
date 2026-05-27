<?php

namespace Drupal\Core\Database\Query;

/**
 * Exception thrown if an insert query doesn't specify insert or default fields.
 */
class NoFieldsException extends \InvalidArgumentException implements \Drupal\Core\Database\DatabaseException {}
