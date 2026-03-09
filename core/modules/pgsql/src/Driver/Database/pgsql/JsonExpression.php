<?php

declare(strict_types=1);

namespace Drupal\pgsql\Driver\Database\pgsql;

use Drupal\Core\Database\Connection;
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
    $segments = array_map(fn($s) => "'" . $s . "'", $this->pathSegments());
    return "{$this->field} #>> ARRAY[" . implode(',', $segments) . "]::text[]";
  }

  /**
   * {@inheritdoc}
   */
  public function toCastSql(JsonCastType $type): string {
    $sql = $this->toSql();
    return match ($type) {
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
    return "{$this->field} @?? '{$this->path}'";
  }

  /**
   * {@inheritdoc}
   */
  public function toContainsSql(Connection $connection, string $encodedValue): string {
    // Values are embedded inside single-quoted SQL string literals (jsonpath
    // expressions). Use quote() for driver-correct escaping, then strip the
    // surrounding quotes it adds since we're inside an existing literal.
    $sqlValue = substr($connection->quote($encodedValue), 1, -1);
    return "JSONB_PATH_QUERY_ARRAY({$this->field}, '{$this->path}') @> '[{$sqlValue}]'";
  }

  /**
   * {@inheritdoc}
   */
  public function toComparisonSql(string $operator, string|int|float|bool $value, Connection $connection, PlaceholderInterface $queryPlaceholder): array {
    // PgSQL uses jsonpath operators for scalar comparisons, which bake the
    // value into the SQL expression instead of using placeholders.
    $op = $operator === '=' ? '==' : $operator;
    $encoded = in_array(gettype($value), ['string', 'boolean', 'array'])
      ? json_encode($value, JSON_THROW_ON_ERROR)
      : $value;
    // Values are embedded inside single-quoted SQL string literals (jsonpath
    // expressions). Use quote() for driver-correct escaping, then strip the
    // surrounding quotes it adds since we're inside an existing literal.
    $sqlValue = substr($connection->quote((string) $encoded), 1, -1);
    // PDO requires the ? be doubled else they are considered placeholders.
    return [
      'sql' => "{$this->field} @?? '{$this->path} ? (@ {$op} {$sqlValue})'",
      'args' => [],
    ];
  }

  /**
   * Converts a JSONPath to PostgreSQL path segments.
   *
   * Transforms "$.a.b[0].c" into ['a', 'b', '0', 'c'].
   *
   * @return string[]
   *   Array of path segments.
   */
  protected function pathSegments(): array {
    // Strip leading "$." or "$".
    $path = preg_replace('/^\$\.?/', '', $this->path);
    // Split on dots and brackets.
    return preg_split('/\.|\[(\d+)]/', $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
  }

}
