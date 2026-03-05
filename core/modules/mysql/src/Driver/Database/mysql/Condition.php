<?php

declare(strict_types=1);

namespace Drupal\mysql\Driver\Database\mysql;

use Drupal\Core\Database\Query\Condition as QueryCondition;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PlaceholderInterface;

/**
 * MySQL implementation of \Drupal\Core\Database\Query\Condition.
 */
class Condition extends QueryCondition {

  /**
   * {@inheritdoc}
   */
  protected function processJsonCondition(array $condition, Connection $connection, bool &$ignore_operator, PlaceholderInterface $query_placeholder): string {
    if ($condition['operator'] === '@>') {
      $ignore_operator = TRUE;
      $value = in_array(gettype($condition['value']), ['string', 'boolean', 'array'])
        // These values are not bound using a named parameter, however encoding
        // string values in particular provides a hedge against SQL injection.
        ? json_encode($condition['value'], JSON_THROW_ON_ERROR)
        : $condition['value'];
      return "JSON_CONTAINS({$condition['field']}, '{$value}', '{$condition['jsonpath']}')";
    }
    // Use CAST expressions that match the functional index definitions so
    // MySQL's optimizer can use the index. The CAST type is inferred from
    // the PHP value type.
    $type = match (TRUE) {
      is_int($condition['value']) => 'int',
      is_float($condition['value']) => 'float',
      is_bool($condition['value']) => 'bool',
      default => 'text',
    };
    if ($type === 'bool') {
      // Boolean JSON values are extracted as JSON true/false; compare with
      // CAST AS SIGNED which converts true→1, false→0.
      $ignore_operator = TRUE;
      return "CAST(JSON_EXTRACT({$condition['field']}, '{$condition['jsonpath']}') AS SIGNED) = " . (int) $condition['value'];
    }
    return Schema::getTypedJsonExtractExpression($condition['field'], $condition['jsonpath'], $type);
  }

}
