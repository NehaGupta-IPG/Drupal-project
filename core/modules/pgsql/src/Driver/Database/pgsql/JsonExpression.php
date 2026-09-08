<?php

declare(strict_types=1);

namespace Drupal\pgsql\Driver\Database\pgsql;

use Drupal\Core\Database\Query\JsonCastType;
use Drupal\Core\Database\Query\JsonExpression as BaseJsonExpression;
use Drupal\Core\Database\Query\PlaceholderInterface;

// cSpell:ignore bigserial ilike

/**
 * PostgreSQL implementation of JsonExpression.
 */
class JsonExpression extends BaseJsonExpression {

  /**
   * {@inheritdoc}
   */
  protected function doToSql(): string {
    // Uses the #>> operator to extract a JSON value as text.
    return "{$this->field} #>> {$this->pathArraySql()}";
  }

  /**
   * Builds the path array the #> and #>> operators take.
   *
   * Uses an ARRAY[] constructor instead of a '{a,b}' literal. Each segment is
   * quoted with quote() on its own, so a key may hold any character. A
   * '{a,b}' literal would need its own escaping for commas, quotes and braces.
   *
   * @return string
   *   A SQL text array of the path segments.
   */
  protected function pathArraySql(): string {
    $segments = array_map(fn($s) => $this->connection->quote($s), $this->pathSegments);
    return 'ARRAY[' . implode(',', $segments) . ']::text[]';
  }

  /**
   * {@inheritdoc}
   */
  protected function doToCastSql(JsonCastType $type, ?string $expression = NULL, ?string $type_expression = NULL): string {
    $sql = $expression ?? $this->toSql();
    return match ($type) {
      JsonCastType::Bool => "({$sql})::boolean::integer",
      // Cast to bigint, not integer. A JSON number can hold any 64-bit value,
      // MySQL's SIGNED and SQLite's INTEGER are 64-bit, and PostgreSQL raises
      // an out of range error instead of truncating a value that does not fit.
      JsonCastType::Int => "({$sql})::bigint",
      JsonCastType::Float => "({$sql})::double precision",
      // Without the parentheses, the ::text the driver appends before LIKE and
      // ILIKE (see Connection::prepareStatement() and
      // Connection::$postgresqlConditionOperatorMap) would attach to the
      // ARRAY[]::text[] operand and turn the path into plain text.
      JsonCastType::Text => "({$sql})",
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function doToHasKeySql(): string {
    // @? tests whether the jsonpath matches any item. PDO reads a lone ? as a
    // positional placeholder, so it is written as ?? here.
    // @see https://www.php.net/manual/en/pdo.prepare.php
    return "{$this->field} @?? {$this->connection->quote($this->path)}";
  }

  /**
   * {@inheritdoc}
   */
  protected function doToContainsSql(string $encoded_value): string {
    // quote() returns the value with surrounding quotes. Strip them, because
    // the value goes inside the single-quoted jsonb array literal on the right
    // of @>. toComparisonSql() and wildcardRowSet() embed the path into a
    // jsonpath literal the same way.
    $sql_value = substr($this->connection->quote($encoded_value), 1, -1);
    $quoted_path = $this->connection->quote($this->path);
    return "JSONB_PATH_QUERY_ARRAY({$this->field}, {$quoted_path}) @> '[{$sql_value}]'";
  }

  /**
   * {@inheritdoc}
   */
  public function toComparisonSql(string $operator, string|int|float|bool|array $value, PlaceholderInterface $queryPlaceholder): array {
    // The base class builds IN and NOT IN from placeholders on the cast value
    // and expands a wildcard path through wildcardRowSet().
    if (is_array($value)) {
      return parent::toComparisonSql($operator, $value, $queryPlaceholder);
    }

    // A comparison against a single value uses jsonpath operators. The value
    // is part of the SQL text, not a placeholder.
    $op = $operator === '=' ? '==' : $operator;
    $encoded = is_string($value) || is_bool($value)
      ? json_encode($value, JSON_THROW_ON_ERROR)
      : $value;
    // Escape the encoded value before embedding it in the jsonpath predicate.
    $sql_value = substr($this->connection->quote((string) $encoded), 1, -1);
    // Escape the path before embedding it in the same predicate.
    $escaped_path = substr($this->connection->quote($this->path), 1, -1);
    // Double the ? in @? for PDO, see doToHasKeySql().
    return [
      'sql' => "{$this->field} @?? '{$escaped_path} ? (@ {$op} {$sql_value})'",
      'args' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function wildcardRowSet(): array {
    // jsonb_path_query() accepts the wildcard path as is and returns one row
    // per matched value, so the path is not split at the wildcard like on
    // MySQL and SQLite. '{}' is an empty path array, so v #>> '{}' reads the
    // row's own value as text.
    // Escape the path before embedding it in the function call.
    $escaped_path = substr($this->connection->quote($this->path), 1, -1);
    return [
      'from' => "jsonb_path_query({$this->field}::jsonb, '{$escaped_path}') AS v",
      'value' => "v #>> '{}'",
      'type' => 'JSONB_TYPEOF(v)',
      'filter' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function doToTypeGuardSql(JsonCastType $type, ?string $type_expression = NULL): string {
    // jsonb_typeof() reports every number as 'number', so one type name covers
    // both numeric casts.
    // @see https://www.postgresql.org/docs/current/functions-json.html
    $json_type = match ($type) {
      JsonCastType::Int, JsonCastType::Float => 'number',
      JsonCastType::Bool => 'boolean',
      JsonCastType::Text => 'string',
    };
    // JSONB_TYPEOF() needs a jsonb value, so the #> operator is used here
    // instead of the #>> operator toSql() uses to extract text.
    $type_expression ??= "JSONB_TYPEOF({$this->field}::jsonb #> {$this->pathArraySql()})";
    return "{$type_expression} = '{$json_type}'";
  }

}
