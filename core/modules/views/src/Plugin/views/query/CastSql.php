<?php

namespace Drupal\views\Plugin\views\query;

/**
 * Cast handling in SQL.
 */
class CastSql implements CastSqlInterface {

  /**
   * {@inheritdoc}
   */
  public function getFieldAsInt(string $field): string {
    return "CAST($field AS INTEGER)";
  }

  /**
   * {@inheritdoc}
   */
  public function getComparableOperands(array $left, array $right): array {
    // Most engines convert the operands of a comparison themselves, so the
    // column types do not have to be looked up.
    return [$left['expression'], $right['expression']];
  }

}
