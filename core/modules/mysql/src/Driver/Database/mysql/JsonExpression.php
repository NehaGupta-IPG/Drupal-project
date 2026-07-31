<?php

declare(strict_types=1);

namespace Drupal\mysql\Driver\Database\mysql;

use Drupal\Core\Database\Query\JsonCastType;
use Drupal\Core\Database\Query\JsonExpression as BaseJsonExpression;
use Drupal\Core\Database\Query\PlaceholderInterface;

/**
 * MySQL implementation of JsonExpression.
 */
class JsonExpression extends BaseJsonExpression {

  /**
   * {@inheritdoc}
   */
  public function toSql(): string {
    return "JSON_EXTRACT({$this->field}, {$this->connection->quote($this->path)})";
  }

  /**
   * {@inheritdoc}
   */
  public function toCastSql(JsonCastType $type, ?string $expression = NULL): string {
    $extract = $expression ?? $this->toSql();
    return match ($type) {
      // MariaDB's CAST(<JSON boolean> AS SIGNED) returns 0 for both true and
      // false, so compare against the JSON literal 'true' to get a 1/0 result
      // that works on both MySQL and MariaDB.
      JsonCastType::Bool => "({$extract} = JSON_EXTRACT('true', '$'))",
      JsonCastType::Int => "CAST({$extract} AS SIGNED)",
      JsonCastType::Float => "CAST({$extract} AS DOUBLE)",
      JsonCastType::Text => "CAST(JSON_UNQUOTE({$extract}) AS CHAR(255))",
    };
  }

  /**
   * {@inheritdoc}
   */
  public function toHasKeySql(): string {
    return "JSON_CONTAINS_PATH({$this->field}, 'one', {$this->connection->quote($this->path)})";
  }

  /**
   * {@inheritdoc}
   */
  public function toContainsSql(string $encoded_value): string {
    $quoted = $this->connection->quote($encoded_value);
    return "JSON_CONTAINS({$this->field}, {$quoted}, {$this->connection->quote($this->path)})";
  }

  /**
   * {@inheritdoc}
   */
  public function toComparisonSql(string $operator, string|int|float|bool|array $value, PlaceholderInterface $queryPlaceholder): array {
    if (!$this->hasWildcard()) {
      return parent::toComparisonSql($operator, $value, $queryPlaceholder);
    }

    // JSON_EXTRACT with a wildcard path returns a JSON array. Use JSON_TABLE
    // to iterate the elements so any comparison operator can be applied.
    // A scalar subquery with COUNT(*) > 0 is used instead of EXISTS because
    // MySQL 8.0 has a bug where EXISTS with correlated JSON_TABLE returns
    // incorrect results when evaluating multiple rows.
    // @see https://bugs.mysql.com/bug.php?id=110819
    [$prefix, $suffix] = $this->splitAtWildcard();
    $extract = $suffix === '$'
      ? 'jt.val'
      : "JSON_EXTRACT(jt.val, {$this->connection->quote($suffix)})";
    $json_table_path = $this->connection->quote($prefix . '.*');

    ['args' => $args, 'cast_type' => $cast_type, 'placeholders' => $placeholders] = $this->buildInPlaceholders((array) $value, $queryPlaceholder);
    $type_guard = $this->toTypeGuardSql($cast_type, "JSON_TYPE({$extract})");
    return [
      'sql' => "(SELECT COUNT(*) FROM JSON_TABLE({$this->field}, {$json_table_path} COLUMNS (val JSON PATH '$')) jt WHERE {$type_guard}{$this->toCastSql($cast_type, $extract)} {$operator} ({$placeholders})) > 0",
      'args' => $args,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function toTypeGuardSql(JsonCastType $type, ?string $type_expression = NULL): string {
    // MySQL reports an unsigned integer and a high precision number as their
    // own type, MariaDB reports both as INTEGER and DOUBLE. A whole number
    // satisfies a float comparison, so it is accepted for both numeric casts.
    $json_types = match ($type) {
      JsonCastType::Int => "'INTEGER', 'UNSIGNED INTEGER'",
      JsonCastType::Float => "'DOUBLE', 'DECIMAL', 'INTEGER', 'UNSIGNED INTEGER'",
      JsonCastType::Bool => "'BOOLEAN'",
      JsonCastType::Text => '',
    };
    if ($json_types === '') {
      return '';
    }
    $type_expression ??= "JSON_TYPE({$this->toSql()})";
    return "{$type_expression} IN ({$json_types}) AND ";
  }

}
