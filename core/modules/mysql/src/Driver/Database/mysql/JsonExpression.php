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
  protected function doToCastSql(JsonCastType $type, ?string $expression = NULL, ?string $type_expression = NULL): string {
    $extract = $expression ?? $this->toSql();
    return match ($type) {
      // MariaDB stores JSON as LONGTEXT, so JSON_EXTRACT() returns the text
      // 'true' or 'false', and CAST(... AS SIGNED) turns either into 0.
      // Compare against the JSON literal 'true' instead to get a 1/0 result
      // that works on both MySQL and MariaDB.
      // @see https://mariadb.com/kb/en/json-data-type/
      JsonCastType::Bool => "({$extract} = JSON_EXTRACT('true', '$'))",
      JsonCastType::Int => "CAST({$extract} AS SIGNED)",
      JsonCastType::Float => "CAST({$extract} AS DOUBLE)",
      // JSON_UNQUOTE() turns a JSON null into the string 'null', so map it to
      // SQL NULL first, like the text extraction on PostgreSQL and SQLite. A
      // plain NULLIF would also swallow a stored string "null". JSON_UNQUOTE()
      // has no length limit. CAST(... AS CHAR) needs a length and uses no more
      // than that many characters of the value.
      // @see https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_cast
      JsonCastType::Text => "CASE WHEN JSON_TYPE({$extract}) = 'NULL' THEN NULL ELSE JSON_UNQUOTE({$extract}) END",
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
    // JSON_CONTAINS() treats a scalar or an object as contained in an array
    // when any element of the array contains it. PostgreSQL and SQLite only
    // match an array against an array and an object against an object, so
    // the JSON_TYPE() test enforces the same here. $encoded_value is JSON
    // text, so it starts with '[' for an array and '{' for an object.
    // @see https://dev.mysql.com/doc/refman/8.4/en/json-search-functions.html#function_json-contains
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
    // JSON_TABLE iterates the values the accessor selects. $.* selects the
    // members of an object and $[*] the elements of an array.
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
    // MySQL's JSON_TYPE() reports a number as INTEGER, UNSIGNED INTEGER,
    // DECIMAL or DOUBLE. MariaDB reports it as INTEGER or DOUBLE only. A
    // whole number satisfies a float comparison, so the integer types are
    // accepted for both numeric casts.
    // @see https://dev.mysql.com/doc/refman/8.4/en/json-attribute-functions.html#function_json-type
    // @see https://mariadb.com/kb/en/json_type/
    $json_types = match ($type) {
      JsonCastType::Int => "'INTEGER', 'UNSIGNED INTEGER'",
      JsonCastType::Float => "'DOUBLE', 'DECIMAL', 'INTEGER', 'UNSIGNED INTEGER'",
      JsonCastType::Bool => "'BOOLEAN'",
      JsonCastType::Text => "'STRING'",
    };
    $type_expression ??= "JSON_TYPE({$this->toSql()})";
    return "{$type_expression} IN ({$json_types})";
  }

}
