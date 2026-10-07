<?php

namespace Drupal\Core\Database;

/**
 * Wrapper base class for exceptions that provide additional debug information.
 *
 * This class will always wrap a client connection exception, for example
 * \PDOException or \mysqli_sql_exception.
 */
class DatabaseExceptionWrapper extends \RuntimeException implements DatabaseException {

  public function __construct(
    string $message = "",
    int $code = 0,
    ?\Throwable $previous = NULL,
    public readonly ?SqlExecutionInfo $info = NULL,
  ) {
    parent::__construct($message, $code, $previous);
  }

}
