<?php

declare(strict_types=1);

namespace Drupal\sqlite\Driver\Database\sqlite;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Condition as QueryCondition;
use Drupal\Core\Database\Query\PlaceholderInterface;

/**
 * SQLite implementation of \Drupal\Core\Database\Query\Condition.
 */
class Condition extends QueryCondition {

  /**
   * {@inheritdoc}
   */
  protected function processJsonCondition(array $condition, Connection $connection, bool &$ignore_operator, PlaceholderInterface $query_placeholder): string {
    // SQLite's json_extract() returns integer 1/0 for boolean values,
    // so we append the comparison directly and skip the operator.
    $ignore_operator = is_bool($condition['value']);
    $fragment = Schema::getJsonExtractValueExpression($condition['field'], $condition['jsonpath']);
    if (is_bool($condition['value'])) {
      $fragment .= ' = ' . (int) $condition['value'];
    }
    return $fragment;
  }

}
