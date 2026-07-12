<?php

namespace Drupal\Core\Database;

/**
 * Base class for a database exception that includes execution information.
 */
abstract class DatabaseExceptionBase extends \RuntimeException implements DatabaseException {

  public function __construct(
    string $message = "",
    int $code = 0,
    ?\Throwable $previous = NULL,
    public readonly ?DatabaseExceptionInfo $info = NULL,
  ) {
    parent::__construct($message, $code, $previous);
  }

}
