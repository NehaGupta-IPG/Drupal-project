<?php

declare(strict_types=1);

namespace Drupal\sqlite\Driver\Database\sqlite;

use Drupal\Core\Database\Query\JsonCastType;
use Drupal\Core\Database\Query\JsonExpression as BaseJsonExpression;
use Drupal\Core\Database\Query\PlaceholderInterface;

/**
 * SQLite implementation of JsonExpression.
 */
class JsonExpression extends BaseJsonExpression {

  /**
   * {@inheritdoc}
   */
  protected function doToSql(): string {
    return "json_extract({$this->field}, {$this->connection->quote($this->path)})";
  }

  /**
   * {@inheritdoc}
   */
  protected function doToCastSql(JsonCastType $type, ?string $expression = NULL): string {
    $sql = $expression ?? $this->toSql();
    return match ($type) {
      JsonCastType::Bool, JsonCastType::Int => "CAST({$sql} AS INTEGER)",
      JsonCastType::Float => "CAST({$sql} AS REAL)",
      JsonCastType::Text => $sql,
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function doToHasKeySql(): string {
    return "json_type({$this->field}, {$this->connection->quote($this->path)}) IS NOT NULL";
  }

  /**
   * {@inheritdoc}
   */
  protected function doToContainsSql(string $encoded_value): string {
    $quoted = $this->connection->quote($encoded_value);
    return "EXISTS (SELECT 1 FROM json_each({$this->field}, {$this->connection->quote($this->path)}) WHERE json_each.value IN (SELECT value FROM json_each({$quoted})))";
  }

  /**
   * {@inheritdoc}
   */
  public function toComparisonSql(string $operator, string|int|float|bool|array $value, PlaceholderInterface $queryPlaceholder): array {
    if (!$this->hasWildcard()) {
      return parent::toComparisonSql($operator, $value, $queryPlaceholder);
    }

    // SQLite doesn't support wildcards in json_extract. Use json_each to
    // iterate the wildcard level and check each element's value for the
    // remaining suffix path.
    [$prefix, $suffix] = $this->splitAtWildcard();
    if ($suffix === '$') {
      $extract = 'je.value';
      $element_type = 'je.type';
      $type_guard = '';
    }
    else {
      // json_extract on a scalar je.value (e.g. a string) causes a
      // "malformed JSON" error. Filter to object/array values only.
      $extract = "json_extract(je.value, {$this->connection->quote($suffix)})";
      $element_type = "json_type(je.value, {$this->connection->quote($suffix)})";
      $type_guard = "je.type IN ('object', 'array') AND ";
    }

    ['args' => $args, 'cast_type' => $cast_type, 'placeholders' => $placeholders] = $this->buildInPlaceholders((array) $value, $queryPlaceholder);
    $type_guard .= $this->toTypeGuardSql($cast_type, $element_type);
    return [
      'sql' => "EXISTS (SELECT 1 FROM json_each({$this->field}, {$this->connection->quote($prefix)}) je WHERE {$type_guard}{$this->toCastSql($cast_type, $extract)} {$operator} ({$placeholders}))",
      'args' => $args,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function doToTypeGuardSql(JsonCastType $type, ?string $type_expression = NULL): string {
    // SQLite has no boolean JSON type, it reports the literals true and false
    // as their own type. A whole number satisfies a float comparison, so
    // 'integer' is accepted for both numeric casts.
    $json_types = match ($type) {
      JsonCastType::Int, JsonCastType::Float => "'integer', 'real'",
      JsonCastType::Bool => "'true', 'false'",
      JsonCastType::Text => '',
    };
    if ($json_types === '') {
      return '';
    }
    $type_expression ??= "json_type({$this->field}, {$this->connection->quote($this->path)})";
    return "{$type_expression} IN ({$json_types}) AND ";
  }

}
