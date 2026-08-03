<?php

declare(strict_types=1);

namespace Drupal\mysql\Driver\Database\mysql;

use Drupal\Core\Database\Query\JsonCastType;
use Drupal\Core\Database\Query\JsonExpression as BaseJsonExpression;

/**
 * MySQL implementation of JsonExpression.
 */
class JsonExpression extends BaseJsonExpression {

  /**
   * {@inheritdoc}
   */
  protected function doToSql(): string {
    return "JSON_EXTRACT({$this->field}, {$this->connection->quote($this->path)})";
  }

  /**
   * {@inheritdoc}
   */
  protected function doToCastSql(JsonCastType $type, ?string $expression = NULL): string {
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
  protected function doToHasKeySql(): string {
    return "JSON_CONTAINS_PATH({$this->field}, 'one', {$this->connection->quote($this->path)})";
  }

  /**
   * {@inheritdoc}
   */
  protected function doToContainsSql(string $encoded_value): string {
    $quoted = $this->connection->quote($encoded_value);
    // JSON_CONTAINS() reports a candidate that is not an array as contained
    // when an element of a target array contains it. PostgreSQL and SQLite
    // require the candidate and the value at the path to be of the same JSON
    // type, so the type test makes an object candidate compare against an
    // object here as well. The encoded candidate is a JSON document, which
    // writes an array as [ ... ] and an object as { ... }.
    $type = str_starts_with($encoded_value, '[') ? 'ARRAY' : 'OBJECT';
    return "(JSON_TYPE({$this->toSql()}) = '{$type}' AND JSON_CONTAINS({$this->field}, {$quoted}, {$this->connection->quote($this->path)}))";
  }

  /**
   * {@inheritdoc}
   */
  protected function wildcardRowSet(): array {
    // JSON_EXTRACT with a wildcard path returns a JSON array. JSON_TABLE
    // iterates the elements instead, so a test can be applied to each of them.
    [$prefix, $suffix] = $this->splitAtWildcard();
    $extract = $suffix === '$'
      ? 'jt.val'
      : "JSON_EXTRACT(jt.val, {$this->connection->quote($suffix)})";
    // JSON_TABLE iterates what the accessor selects: .* the members of an
    // object, [*] the elements of an array.
    $json_table_path = $this->connection->quote($prefix . $this->wildcardAccessor());
    return [
      'from' => "JSON_TABLE({$this->field}, {$json_table_path} COLUMNS (val JSON PATH '$')) jt",
      'value' => $extract,
      'type' => "JSON_TYPE({$extract})",
      'filter' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function wildcardMatchSql(string $from, string $where): string {
    // A scalar subquery with COUNT(*) > 0 is used instead of EXISTS because
    // MySQL 8.0 has a bug where EXISTS with correlated JSON_TABLE returns
    // incorrect results when evaluating multiple rows.
    // @see https://bugs.mysql.com/bug.php?id=110819
    return "(SELECT COUNT(*) FROM {$from} WHERE {$where}) > 0";
  }

  /**
   * {@inheritdoc}
   */
  protected function doToTypeGuardSql(JsonCastType $type, ?string $type_expression = NULL): string {
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
