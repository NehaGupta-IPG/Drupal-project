<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\InvalidQueryException;

/**
 * Generates a SQL fragment for extracting a scalar value from a JSON column.
 *
 * Each database driver provides its own subclass with driver-specific SQL.
 * Used internally by JsonCondition and Select::jsonOrderBy(). Can also be
 * used directly to add JSON extractions to a SELECT field list:
 * @code
 * $expr = $connection->jsonExpression('field_data', '$.status');
 * $query->addExpression($expr->toSql(), 'status');
 * // With a type cast:
 * $query->addExpression($expr->toCastSql(JsonCastType::Int), 'score');
 * @endcode
 */
abstract class JsonExpression {

  /**
   * The column name.
   */
  protected readonly string $field;

  /**
   * The normalized JSONPath expression.
   *
   * All key names are in a format supported by all database drivers:
   * - Simple alphanumeric keys use dot notation: $.key
   * - Keys with special characters use double-quoted dot notation: $."key"
   * - Array indices are unchanged: $[0]
   */
  protected readonly string $path;

  /**
   * The parsed path segments (key names and array indices as strings).
   *
   * @var string[]
   */
  protected readonly array $pathSegments;

  /**
   * Constructs a JsonExpression object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param string $field
   *   The column name, optionally table-qualified (e.g. "t.data").
   * @param string $path
   *   The JSONPath expression. Supported formats include dot notation
   *   ($.key, $.nested.path), bracket notation ($["key"]), array indices
   *   ($[0], $.array[1]), and mixed ($.parent["child-key"][0].name).
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If the path does not match the allowed pattern.
   */
  public function __construct(
    protected readonly Connection $connection,
    string $field,
    string $path,
  ) {
    $this->field = $connection->escapeField($field);

    $this->validatePath($path);
    $this->pathSegments = $this->parseSegments($path);
    $this->path = $this->normalizePath($path);
  }

  /**
   * Generates the SQL extraction expression.
   *
   * @return string
   *   A SQL fragment that extracts the value at the given path.
   */
  abstract public function toSql(): string;

  /**
   * Generates a SQL extraction expression with a type cast.
   *
   * Used for ORDER BY and other contexts where the extracted JSON value
   * must be cast to a specific SQL type for correct sorting/comparison.
   *
   * @param \Drupal\Core\Database\Query\JsonCastType $type
   *   The cast type.
   *
   * @return string
   *   A SQL fragment that extracts and casts the value at the given path.
   */
  abstract public function toCastSql(JsonCastType $type): string;

  /**
   * Generates SQL to check if the JSON path exists (has a key/element).
   *
   * @return string
   *   A SQL fragment that evaluates to TRUE if the path exists.
   */
  abstract public function toHasKeySql(): string;

  /**
   * Generates SQL for JSON array containment check.
   *
   * @param string $encoded_value
   *   The JSON-encoded value to check for containment.
   *
   * @return string
   *   A SQL fragment that evaluates to TRUE if the JSON array at the path
   *   contains the given value.
   */
  abstract public function toContainsSql(string $encoded_value): string;

  /**
   * Generates SQL for a scalar or boolean comparison.
   *
   * The default implementation uses extract+placeholder, which works for
   * MySQL and SQLite. PgSQL overrides this to use jsonpath operators where
   * values are embedded directly in the SQL expression.
   *
   * @param string $operator
   *   The comparison operator (=, <>, <, >, etc.).
   * @param string|int|float|bool $value
   *   The value to compare against.
   * @param \Drupal\Core\Database\Query\PlaceholderInterface $queryPlaceholder
   *   The placeholder interface for generating unique placeholders.
   *
   * @return array{sql: string, args: array}
   *   An array with 'sql' (the SQL fragment) and 'args' (placeholder values).
   */
  public function toComparisonSql(string $operator, string|int|float|bool $value, PlaceholderInterface $queryPlaceholder): array {
    if (is_bool($value)) {
      return [
        'sql' => "{$this->toCastSql(JsonCastType::Int)} = " . (int) $value,
        'args' => [],
      ];
    }

    $type = JsonCastType::fromPhpValue($value);
    $placeholder = ':db_condition_placeholder_' . $queryPlaceholder->nextPlaceholder();
    return [
      'sql' => "{$this->toCastSql($type)} {$operator} {$placeholder}",
      'args' => [$placeholder => $value],
    ];
  }

  /**
   * Whether this expression's path contains a wildcard (*) segment.
   */
  public function hasWildcard(): bool {
    return in_array('*', $this->pathSegments, TRUE);
  }

  /**
   * Splits the path at the first wildcard into prefix and suffix.
   *
   * For example, $.level1.*.level2.level3 splits into:
   * - prefix: $.level1
   * - suffix: $.level2.level3
   *
   * @return array{string, string}
   *   A tuple of [prefix, suffix] raw paths.
   */
  protected function splitAtWildcard(): array {
    $parts = explode('.*', $this->path, 2);
    return [$parts[0], '$' . $parts[1]];
  }

  /**
   * Validates a JSONPath expression.
   *
   * Accepts:
   * - Dot notation with simple keys: $.key, $.key-name, $.key_name
   * - Bracket notation with integer indices: $[0], $[42]
   * - Bracket notation with quoted strings: $["any key"], $["esc\"aped"]
   * - Wildcard: $.*, $[*], $.level1.*.level2
   *
   * @param string $path
   *   The JSONPath expression to validate.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If the path does not match the allowed pattern.
   */
  protected function validatePath(string $path): void {
    if (!preg_match('/^\$(\.[\w-]+|\.\*|\[\*]|\[\d+]|\["([^"\\\\]|\\\\.)*"])*$/', $path)) {
      throw new InvalidQueryException(sprintf(
        'The provided jsonpath "%s" is invalid. Use dot notation ($.key), bracket notation ($["key"]), or array indices ($[0]).',
        $path,
      ));
    }
    // Only one wildcard segment is supported. Multiple wildcards would require
    // nested subqueries on SQLite and nested array handling on MySQL.
    if (preg_match_all('/\.\*|\[\*]/', $path) > 1) {
      throw new InvalidQueryException(sprintf(
        'The provided jsonpath "%s" contains multiple wildcards. Only one wildcard segment is supported.',
        $path,
      ));
    }
  }

  /**
   * Normalizes a JSONPath for use in SQL across all database drivers.
   *
   * Converts bracket-quoted strings and dot-notation segments with special
   * characters to double-quoted dot notation. Simple alphanumeric keys and
   * array indices are left unchanged.
   *
   * Example: $.foo.bar-baz[0]["already quoted"] becomes
   *          $.foo."bar-baz"[0]."already quoted"
   *
   * @param string $path
   *   The validated JSONPath expression.
   *
   * @return string
   *   The normalized JSONPath.
   */
  protected function normalizePath(string $path): string {
    // Convert bracket wildcards to dot notation.
    $path = str_replace('[*]', '.*', $path);
    // Convert bracket-quoted strings to double-quoted dot notation.
    // Only unescape \" → " and \\ → \. Other escape sequences like \0, \n,
    // \xNN must not be decoded as they can produce unsafe characters in SQL.
    $path = preg_replace_callback(
      '/\["((?:[^"\\\\]|\\\\.)*)"]/',
      fn($m) => '."' . preg_replace('/\\\\(["\\\\])/', '$1', $m[1]) . '"',
      $path,
    );
    // Quote dot-notation segments that contain non-word characters.
    $path = preg_replace_callback(
      '/\.(\w[\w-]*)/',
      fn($m) => preg_match('/^\w+$/', $m[1]) ? '.' . $m[1] : '."' . $m[1] . '"',
      $path,
    );
    return $path;
  }

  /**
   * Parses a JSONPath into individual path segments.
   *
   * @param string $path
   *   The validated JSONPath expression.
   *
   * @return string[]
   *   The path segments (key names and array indices as strings).
   */
  protected function parseSegments(string $path): array {
    // Strip leading "$" or "$.".
    $path = preg_replace('/^\$\.?/', '', $path);
    $segments = [];
    // Match dot-notation keys, wildcards, bracket-quoted strings, or numeric
    // indices.
    preg_match_all('/\.?([\w-]+)|\.?\*|\[\*]|\["((?:[^"\\\\]|\\\\.)*)"]|\[(\d+)]/', $path, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      if ($match[0] === '.*' || $match[0] === '*' || $match[0] === '[*]') {
        $segments[] = '*';
      }
      elseif ($match[1] !== '') {
        // Dot notation key.
        $segments[] = $match[1];
      }
      elseif (isset($match[2]) && $match[2] !== '') {
        // Bracket-quoted string — unescape only \" and \\.
        $segments[] = preg_replace('/\\\\(["\\\\])/', '$1', $match[2]);
      }
      else {
        // Numeric index.
        $segments[] = $match[3];
      }
    }
    return $segments;
  }

}
