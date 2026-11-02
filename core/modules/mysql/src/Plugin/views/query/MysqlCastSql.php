<?php

namespace Drupal\mysql\Plugin\views\query;

use Drupal\views\Plugin\views\query\CastSql;

/**
 * MySQL specific cast handling.
 */
class MysqlCastSql extends CastSql {

  /**
   * {@inheritdoc}
   */
  public function getFieldAsInt(string $field): string {
    return "CAST($field AS UNSIGNED)";
  }

}
