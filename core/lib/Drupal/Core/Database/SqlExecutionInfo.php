<?php

namespace Drupal\Core\Database;

/**
 * Value object carrying information pertaining to a SQL execution.
 */
class SqlExecutionInfo {

  /**
   * Constructor.
   *
   * @param string $sqlString
   *   The SQL statement string.
   * @param list<array|int|float|string|bool|null>|array<string,array|int|float|string|bool|null> $placeholders
   *   (optional) The placeholder values, keyed by placeholder name for a
   *   string containing named placeholders, 0-indexed for a string containing
   *   positional placeholders.
   * @param array<string,mixed> $options
   *   An associative array of options to control how the database operation is
   *   run.
   */
  public function __construct(
    public readonly string $sqlString,
    public readonly array $placeholders = [],
    public readonly array $options = [],
  ) {
  }

}
