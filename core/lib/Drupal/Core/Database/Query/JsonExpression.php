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
   * Constructs a JsonExpression object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param string $field
   *   The column name, optionally table-qualified (e.g. "t.data").
   * @param string $path
   *   The JSONPath expression (e.g. "$.status"). Must start with "$"
   *   followed by dot notation ($.key) or bracket notation ($[0])
   *   with alphanumeric identifiers.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If the path does not match the allowed pattern.
   */
  public function __construct(
    Connection $connection,
    string $field,
    protected readonly string $path,
  ) {
    $this->field = $connection->escapeField($field);

    if (!preg_match('/^\$(\.\w+|\[\d+\])*$/', $this->path)) {
      throw new InvalidQueryException(sprintf(
        'The provided jsonpath "%s" is invalid. Supported jsonpath expressions must start with "$" and use dot notation ($.key) or bracket notation ($[0]) with alphanumeric identifiers.',
        $this->path,
      ));
    }
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
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection, used for value quoting.
   * @param string $encodedValue
   *   The JSON-encoded value to check for containment.
   *
   * @return string
   *   A SQL fragment that evaluates to TRUE if the JSON array at the path
   *   contains the given value.
   */
  abstract public function toContainsSql(Connection $connection, string $encodedValue): string;

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
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection, used for value quoting if needed.
   * @param \Drupal\Core\Database\Query\PlaceholderInterface $queryPlaceholder
   *   The placeholder interface for generating unique placeholders.
   *
   * @return array{sql: string, args: array}
   *   An array with 'sql' (the SQL fragment) and 'args' (placeholder values).
   */
  public function toComparisonSql(string $operator, string|int|float|bool $value, Connection $connection, PlaceholderInterface $queryPlaceholder): array {
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

}
