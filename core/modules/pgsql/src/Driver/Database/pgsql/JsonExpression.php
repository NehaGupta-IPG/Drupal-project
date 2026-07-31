<?php

declare(strict_types=1);

namespace Drupal\pgsql\Driver\Database\pgsql;

use Drupal\Core\Database\Query\JsonCastType;
use Drupal\Core\Database\Query\JsonExpression as BaseJsonExpression;
use Drupal\Core\Database\Query\PlaceholderInterface;

// cSpell:ignore ilike

/**
 * PostgreSQL implementation of JsonExpression.
 */
class JsonExpression extends BaseJsonExpression {

  /**
   * {@inheritdoc}
   */
  public function toSql(): string {
    // Uses the #>> operator to extract a JSON value as text. Uses ARRAY[]
    // constructor instead of '{a,b}' literal so the curly braces don't need to
    // survive prefixTables() and quoteIdentifiers().
    $segments = array_map(fn($s) => $this->connection->quote($s), $this->pathSegments);
    return "{$this->field} #>> ARRAY[" . implode(',', $segments) . "]::text[]";
  }

  /**
   * {@inheritdoc}
   */
  public function toCastSql(JsonCastType $type, ?string $expression = NULL): string {
    $sql = $expression ?? $this->toSql();
    return match ($type) {
      JsonCastType::Bool => "({$sql})::boolean::integer",
      JsonCastType::Int => "({$sql})::integer",
      JsonCastType::Float => "({$sql})::double precision",
      // Parentheses prevent PgSQL's prepareStatement() from appending ::text to
      // the ARRAY[]::text[] portion when used before LIKE/ILIKE.
      JsonCastType::Text => "({$sql})",
    };
  }

  /**
   * {@inheritdoc}
   */
  public function toHasKeySql(): string {
    // @? checks if the jsonpath returns any items. PDO requires doubling the ?
    // to avoid placeholder interpretation.
    return "{$this->field} @?? {$this->connection->quote($this->path)}";
  }

  /**
   * {@inheritdoc}
   */
  public function toContainsSql(string $encoded_value): string {
    // Values are embedded inside single-quoted SQL string literals (jsonpath
    // expressions). Use quote() for driver-correct escaping, then strip the
    // surrounding quotes it adds since we're inside an existing literal.
    $sql_value = substr($this->connection->quote($encoded_value), 1, -1);
    $quoted_path = $this->connection->quote($this->path);
    return "JSONB_PATH_QUERY_ARRAY({$this->field}, {$quoted_path}) @> '[{$sql_value}]'";
  }

  /**
   * {@inheritdoc}
   */
  public function toComparisonSql(string $operator, string|int|float|bool|array $value, PlaceholderInterface $queryPlaceholder): array {
    // Non-wildcard arrays delegate to the base class.
    if (is_array($value) && !$this->hasWildcard()) {
      return parent::toComparisonSql($operator, $value, $queryPlaceholder);
    }

    // Wildcard IN/NOT IN: use jsonb_path_query to expand the wildcard into a
    // set of values, then cast and compare each value.
    if (is_array($value)) {
      $escaped_path = str_replace("'", "''", $this->path);
      ['args' => $args, 'cast_type' => $cast_type, 'placeholders' => $placeholders] = $this->buildInPlaceholders($value, $queryPlaceholder);
      $type_guard = $this->typeGuard($cast_type);
      return [
        'sql' => "EXISTS (SELECT 1 FROM jsonb_path_query({$this->field}::jsonb, '{$escaped_path}') AS v WHERE {$type_guard}{$this->toCastSql($cast_type, "v #>> '{}'")} {$operator} ({$placeholders}))",
        'args' => $args,
      ];
    }

    // PgSQL uses jsonpath operators for scalar comparisons, which bake the
    // value into the SQL expression instead of using placeholders.
    $op = $operator === '=' ? '==' : $operator;
    $encoded = is_string($value) || is_bool($value)
      ? json_encode($value, JSON_THROW_ON_ERROR)
      : $value;
    // Values are embedded inside single-quoted SQL string literals (jsonpath
    // expressions). Use quote() for driver-correct escaping, then strip the
    // surrounding quotes it adds since we're inside an existing literal.
    $sql_value = substr($this->connection->quote((string) $encoded), 1, -1);
    // The path and value are combined in one SQL string literal. Escape
    // single quotes in the path; $sql_value is already escaped above.
    $escaped_path = str_replace("'", "''", $this->path);
    // PDO requires the ? be doubled else they are considered placeholders.
    return [
      'sql' => "{$this->field} @?? '{$escaped_path} ? (@ {$op} {$sql_value})'",
      'args' => [],
    ];
  }

  /**
   * Restricts a wildcard comparison to the elements of the expected JSON type.
   *
   * Casting a JSON string to a number or a boolean raises SQLSTATE 22P02.
   * Elements of another type never equal the compared value anyway, so they are
   * filtered out before the cast. The scalar comparison above needs no guard,
   * because jsonpath comparisons are type aware.
   *
   * @param \Drupal\Core\Database\Query\JsonCastType $type
   *   The cast type of the comparison.
   *
   * @return string
   *   A SQL fragment ending in ' AND ', or an empty string for a text cast,
   *   which every JSON type survives.
   */
  protected function typeGuard(JsonCastType $type): string {
    $json_type = match ($type) {
      JsonCastType::Int, JsonCastType::Float => 'number',
      JsonCastType::Bool => 'boolean',
      JsonCastType::Text => '',
    };
    return $json_type === '' ? '' : "JSONB_TYPEOF(v) = '{$json_type}' AND ";
  }

}
