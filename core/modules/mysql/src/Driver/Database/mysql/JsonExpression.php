<?php

declare(strict_types=1);

namespace Drupal\mysql\Driver\Database\mysql;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\JsonCastType;
use Drupal\Core\Database\Query\JsonExpression as BaseJsonExpression;

/**
 * MySQL implementation of JsonExpression.
 */
class JsonExpression extends BaseJsonExpression {

  /**
   * {@inheritdoc}
   */
  public function toSql(): string {
    return "JSON_EXTRACT({$this->field}, '{$this->path}')";
  }

  /**
   * {@inheritdoc}
   */
  public function toCastSql(JsonCastType $type): string {
    $extract = $this->toSql();
    return match ($type) {
      JsonCastType::Int => "CAST({$extract} AS SIGNED)",
      JsonCastType::Float => "CAST({$extract} AS DOUBLE)",
      JsonCastType::Text => "CAST(JSON_UNQUOTE({$extract}) AS CHAR(255))",
    };
  }

  /**
   * {@inheritdoc}
   */
  public function toHasKeySql(): string {
    return "JSON_CONTAINS_PATH({$this->field}, 'one', '{$this->path}')";
  }

  /**
   * {@inheritdoc}
   */
  public function toContainsSql(Connection $connection, string $encodedValue): string {
    $quoted = $connection->quote($encodedValue);
    return "JSON_CONTAINS({$this->field}, {$quoted}, '{$this->path}')";
  }

}
