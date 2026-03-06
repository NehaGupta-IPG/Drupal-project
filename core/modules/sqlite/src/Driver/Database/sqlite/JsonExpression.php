<?php

declare(strict_types=1);

namespace Drupal\sqlite\Driver\Database\sqlite;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\JsonCastType;
use Drupal\Core\Database\Query\JsonExpression as BaseJsonExpression;

/**
 * SQLite implementation of JsonExpression.
 */
class JsonExpression extends BaseJsonExpression {

  /**
   * {@inheritdoc}
   */
  public function toSql(): string {
    return "json_extract({$this->field}, '{$this->path}')";
  }

  /**
   * {@inheritdoc}
   */
  public function toCastSql(JsonCastType $type): string {
    $sql = $this->toSql();
    return match ($type) {
      JsonCastType::Int => "CAST({$sql} AS INTEGER)",
      JsonCastType::Float => "CAST({$sql} AS REAL)",
      JsonCastType::Text => $sql,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function toHasKeySql(): string {
    return "json_type({$this->field}, '{$this->path}') IS NOT NULL";
  }

  /**
   * {@inheritdoc}
   */
  public function toContainsSql(Connection $connection, string $encodedValue): string {
    $quoted = $connection->quote($encodedValue);
    return "EXISTS (SELECT 1 FROM json_each({$this->field}, '{$this->path}') WHERE json_each.value IN (SELECT value FROM json_each({$quoted})))";
  }

}
