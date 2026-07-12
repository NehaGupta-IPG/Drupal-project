<?php

namespace Drupal\Core\Database;

/**
 * Value object carrying information pertaining to a database exception.
 */
class DatabaseExceptionInfo {

  public function __construct(
    public readonly ?string $sqlString = NULL,
    public readonly array $placeholders = [],
    public readonly array $options = [],
  ) {
  }

}
