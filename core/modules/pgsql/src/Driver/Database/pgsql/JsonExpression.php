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
   * Uses an ARRAY[] constructor instead of a '{a,b}' literal so the curly
   * braces don't need to survive prefixTables() and quoteIdentifiers().
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
  protected function doToCastSql(JsonCastType $type, ?string $expression = NULL): string {
    $sql = $expression ?? $this->toSql();
    return match ($type) {
      JsonCastType::Bool => "({$sql})::boolean::integer",
      // bigint, not integer: MySQL's SIGNED and SQLite's INTEGER are both
      // 64-bit, and PostgreSQL raises an out of range error instead of
      // truncating. Entity and revision IDs come from bigserial columns.
      JsonCastType::Int => "({$sql})::bigint",
      JsonCastType::Float => "({$sql})::double precision",
      // Parentheses prevent PgSQL's prepareStatement() from appending ::text to
      // the ARRAY[]::text[] portion when used before LIKE/ILIKE.
      JsonCastType::Text => "({$sql})",
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function doToHasKeySql(): string {
    // @? checks if the jsonpath returns any items. PDO requires doubling the ?
    // to avoid placeholder interpretation.
    return "{$this->field} @?? {$this->connection->quote($this->path)}";
  }

  /**
   * {@inheritdoc}
   */
  protected function doToContainsSql(string $encoded_value): string {
    // Escape the encoded value with quote(), then strip the surrounding
    // quotes it adds: the value is embedded inside an existing single-quoted
    // jsonpath literal. The other embedding sites in this class do the same.
    $sql_value = substr($this->connection->quote($encoded_value), 1, -1);
    $quoted_path = $this->connection->quote($this->path);
    return "JSONB_PATH_QUERY_ARRAY({$this->field}, {$quoted_path}) @> '[{$sql_value}]'";
  }

  /**
   * {@inheritdoc}
   */
  public function toComparisonSql(string $operator, string|int|float|bool|array $value, PlaceholderInterface $queryPlaceholder): array {
    // An IN/NOT IN comparison is built from placeholders on a cast value by the
    // base class, which expands a wildcard path through wildcardRowSet().
    if (is_array($value)) {
      return parent::toComparisonSql($operator, $value, $queryPlaceholder);
    }

    // PgSQL uses jsonpath operators for scalar comparisons, which bake the
    // value into the SQL expression instead of using placeholders.
    $op = $operator === '=' ? '==' : $operator;
    $encoded = is_string($value) || is_bool($value)
      ? json_encode($value, JSON_THROW_ON_ERROR)
      : $value;
    // Escape the encoded value before embedding it in the jsonpath predicate.
    $sql_value = substr($this->connection->quote((string) $encoded), 1, -1);
    // Escape the path before embedding it in the same predicate.
    $escaped_path = substr($this->connection->quote($this->path), 1, -1);
    // PDO requires the ? be doubled else they are considered placeholders.
    return [
      'sql' => "{$this->field} @?? '{$escaped_path} ? (@ {$op} {$sql_value})'",
      'args' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function wildcardRowSet(): array {
    // jsonb_path_query returns one row per value the jsonpath matches, so the
    // wildcard needs no rewriting. The #>> operator reads the value of a row as
    // text, with an empty path array addressing the value itself.
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
    // PostgreSQL reports every number as 'number', so one type name covers
    // both numeric casts.
    $json_type = match ($type) {
      JsonCastType::Int, JsonCastType::Float => 'number',
      JsonCastType::Bool => 'boolean',
      JsonCastType::Text => '',
    };
    if ($json_type === '') {
      return '';
    }
    // JSONB_TYPEOF() needs a jsonb value, so the #> operator is used here
    // instead of the #>> operator toSql() uses to extract text.
    $type_expression ??= "JSONB_TYPEOF({$this->field}::jsonb #> {$this->pathArraySql()})";
    return "{$type_expression} = '{$json_type}' AND ";
  }

}
