<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\InvalidQueryException;

/**
 * Generates a SQL fragment for extracting a scalar value from a JSON column.
 *
 * Each database driver provides its own subclass with driver-specific SQL.
 * Used internally by JsonCondition, Select::jsonOrderBy() and
 * Select::addJsonExpression(). To add a JSON extraction to a SELECT field list,
 * use the query builder instead of this class:
 * @code
 * $alias = $query->addJsonExpression('field_data', '$.status', TRUE);
 * // With a type cast:
 * $alias = $query->addJsonExpression('field_data', '$.score', 'score', JsonCastType::Int);
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
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If the path contains a wildcard.
   *
   * @internal
   *   This method should only be used by the database layer.
   */
  public function toSql(): string {
    $this->assertNoWildcard('extracting a value');
    return $this->doToSql();
  }

  /**
   * Generates the SQL extraction expression.
   *
   * @return string
   *   A SQL fragment that extracts the value at the given path.
   */
  abstract protected function doToSql(): string;

  /**
   * Generates a SQL expression with a type cast.
   *
   * When called without $expression, extracts and casts the value at this
   * expression's JSON path. When called with $expression, applies the same
   * driver-specific cast to an arbitrary SQL expression (e.g. a wildcard
   * subquery alias like 'jt.val' or 'je.value').
   *
   * @param \Drupal\Core\Database\Query\JsonCastType $type
   *   The cast type.
   * @param string|null $expression
   *   A SQL expression to cast. If NULL, uses toSql() to extract from the
   *   JSON column.
   *
   * @return string
   *   A SQL fragment that casts the value to the given type.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If the path contains a wildcard and $expression is NULL.
   *
   * @internal
   *   This method should only be used by the database layer.
   */
  public function toCastSql(JsonCastType $type, ?string $expression = NULL): string {
    if ($expression === NULL) {
      $this->assertNoWildcard('casting a value');
    }
    return $this->doToCastSql($type, $expression);
  }

  /**
   * Generates a SQL expression with a type cast.
   *
   * @param \Drupal\Core\Database\Query\JsonCastType $type
   *   The cast type.
   * @param string|null $expression
   *   A SQL expression to cast. If NULL, uses toSql() to extract from the
   *   JSON column.
   *
   * @return string
   *   A SQL fragment that casts the value to the given type.
   */
  abstract protected function doToCastSql(JsonCastType $type, ?string $expression = NULL): string;

  /**
   * Generates SQL to check if the JSON path exists (has a key/element).
   *
   * @return string
   *   A SQL fragment that evaluates to TRUE if the path exists.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If the path contains a wildcard.
   *
   * @internal
   *   This method should only be used by the database layer.
   */
  public function toHasKeySql(): string {
    $this->assertNoWildcard('testing whether a key exists');
    return $this->doToHasKeySql();
  }

  /**
   * Generates SQL to check if the JSON path exists (has a key/element).
   *
   * @return string
   *   A SQL fragment that evaluates to TRUE if the path exists.
   */
  abstract protected function doToHasKeySql(): string;

  /**
   * Generates SQL for JSON array containment check.
   *
   * @param string $encoded_value
   *   The JSON-encoded value to check for containment.
   *
   * @return string
   *   A SQL fragment that evaluates to TRUE if the JSON array at the path
   *   contains the given value.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If the path contains a wildcard.
   *
   * @internal
   *   This method should only be used by the database layer.
   */
  public function toContainsSql(string $encoded_value): string {
    $this->assertNoWildcard('testing array containment');
    return $this->doToContainsSql($encoded_value);
  }

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
  abstract protected function doToContainsSql(string $encoded_value): string;

  /**
   * Generates SQL restricting a comparison to values of the expected JSON type.
   *
   * Casting a JSON value to a type it does not hold is not portable. MySQL and
   * MariaDB raise truncation warning 1292, which becomes an error in a
   * statement that writes data, PostgreSQL raises SQLSTATE 22P02, and SQLite
   * silently returns 0. Comparing only the values that already hold the
   * expected type avoids all three, and gives the same result on every
   * backend, because a value of another type equals no value of the compared
   * type.
   *
   * The guard is combined with AND, so a value of another type matches no
   * operator, including a negated one such as <> or NOT BETWEEN. This is what
   * PostgreSQL's type aware jsonpath comparison does for scalar values.
   *
   * @param \Drupal\Core\Database\Query\JsonCastType $type
   *   The cast type the comparison uses.
   * @param string|null $type_expression
   *   A SQL expression returning the JSON type name of the value to check. If
   *   NULL, the type of the value at this expression's JSON path is used.
   *
   * @return string
   *   A SQL fragment ending in ' AND ', or an empty string when the cast
   *   cannot fail, as is the case for a text cast.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If the path contains a wildcard and $type_expression is NULL.
   *
   * @internal
   *   This method should only be used by the database layer.
   */
  public function toTypeGuardSql(JsonCastType $type, ?string $type_expression = NULL): string {
    if ($type_expression === NULL) {
      $this->assertNoWildcard('testing the type of a value');
    }
    return $this->doToTypeGuardSql($type, $type_expression);
  }

  /**
   * Generates SQL restricting a comparison to values of the expected JSON type.
   *
   * @param \Drupal\Core\Database\Query\JsonCastType $type
   *   The cast type the comparison uses.
   * @param string|null $type_expression
   *   A SQL expression returning the JSON type name of the value to check. If
   *   NULL, the type of the value at this expression's JSON path is used.
   *
   * @return string
   *   A SQL fragment ending in ' AND ', or an empty string when the cast
   *   cannot fail, as is the case for a text cast.
   */
  abstract protected function doToTypeGuardSql(JsonCastType $type, ?string $type_expression = NULL): string;

  /**
   * Generates SQL for a comparison against a scalar or array value.
   *
   * For scalar values, produces a simple comparison (e.g. extract = :p).
   * For array values, produces an IN/NOT IN clause. Drivers override this
   * to expand wildcard paths into row sets using driver-specific mechanisms
   * (JSON_TABLE, json_each, jsonb_path_query).
   *
   * @param string $operator
   *   The comparison operator (=, <>, <, >, IN, NOT IN, etc.).
   * @param string|int|float|bool|array $value
   *   The value to compare against. An array for IN/NOT IN operators.
   * @param \Drupal\Core\Database\Query\PlaceholderInterface $queryPlaceholder
   *   The placeholder interface for generating unique placeholders.
   *
   * @return array{sql: string, args: array}
   *   An array with 'sql' (the SQL fragment) and 'args' (placeholder values).
   *
   * @internal
   *   This method should only be used by the database layer.
   */
  public function toComparisonSql(string $operator, string|int|float|bool|array $value, PlaceholderInterface $queryPlaceholder): array {
    ['args' => $args, 'cast_type' => $cast_type, 'placeholders' => $placeholders] = $this->buildInPlaceholders((array) $value, $queryPlaceholder);
    $sql = "{$this->toCastSql($cast_type)} {$operator} ({$placeholders})";
    $type_guard = $this->toTypeGuardSql($cast_type);
    return [
      'sql' => $type_guard === '' ? $sql : "({$type_guard}{$sql})",
      'args' => $args,
    ];
  }

  /**
   * Builds placeholder arguments for an IN/NOT IN comparison.
   *
   * @param array $value
   *   The array of values.
   * @param \Drupal\Core\Database\Query\PlaceholderInterface $queryPlaceholder
   *   The placeholder interface for generating unique placeholders.
   *
   * @return array{args: array, cast_type: JsonCastType, placeholders: string}
   *   An array with 'args' (placeholder-to-value map), 'cast_type' (the
   *   inferred cast type), and 'placeholders' (comma-separated tokens).
   */
  protected function buildInPlaceholders(array $value, PlaceholderInterface $queryPlaceholder): array {
    $args = [];
    $first_value = $value[array_key_first($value)];
    $cast_to_int = is_bool($first_value);
    foreach ($value as $val) {
      $placeholder = ':db_condition_placeholder_' . $queryPlaceholder->nextPlaceholder();
      $args[$placeholder] = $cast_to_int ? (int) $val : $val;
    }
    return [
      'args' => $args,
      'cast_type' => JsonCastType::fromPhpValue($first_value),
      'placeholders' => implode(', ', array_keys($args)),
    ];
  }

  /**
   * Whether this expression's path contains a wildcard (*) segment.
   *
   * @internal
   *   This method should only be used by the database layer and database
   *   drivers.
   */
  public function hasWildcard(): bool {
    return in_array('*', $this->pathSegments, TRUE);
  }

  /**
   * Throws when the path holds a wildcard the SQL form cannot express.
   *
   * A wildcard matches any number of values. toComparisonSql() expands it into
   * a row set and asks whether any of the matched values satisfies the
   * comparison. The other forms produce a single value, so they have nowhere
   * to expand the wildcard into. Without this check each backend degrades in
   * its own way: PostgreSQL looks for a literal "*" key and returns NULL,
   * SQLite raises an error, and MySQL returns the whole matched set.
   *
   * @param string $form
   *   What the caller asked the expression for, used in the message.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If the path contains a wildcard.
   */
  protected function assertNoWildcard(string $form): void {
    if ($this->hasWildcard()) {
      throw new InvalidQueryException(sprintf(
        'The jsonpath "%s" contains a wildcard, which is not supported when %s. A wildcard path is supported in a comparison condition only, with one of the operators: %s.',
        $this->path,
        $form,
        implode(', ', JsonCondition::WILDCARD_OPERATORS),
      ));
    }
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
