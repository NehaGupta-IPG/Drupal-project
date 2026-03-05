<?php

declare(strict_types=1);

namespace Drupal\mysql\Driver\Database\mysql;

use Drupal\Core\Database\JsonpathGeneratedFieldConditionTrait;
use Drupal\Core\Database\Query\Condition as QueryCondition;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PlaceholderInterface;
use Drupal\mysql\Driver\Database\mysql\Connection as MySqlConnection;

/**
 * MySQL implementation of \Drupal\Core\Database\Query\Condition.
 */
class Condition extends QueryCondition {

  use JsonpathGeneratedFieldConditionTrait {
    getJsonFieldFragment as doGetJsonFieldFragment;
    getJsonFieldFragmentFunction as doGetJsonFieldFragmentFunction;
  }

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
    return $this->getJsonFieldFragment($condition['field'], $condition, $connection, $query_placeholder);
  }

  /**
   * {@inheritdoc}
   */
  protected function getJsonFieldFragment($field_name, array $condition, Connection $connection, PlaceholderInterface $query_placeholder): string {
    assert($connection instanceof MySqlConnection);
    return $this->doGetJsonFieldFragment(
      $field_name,
      $condition,
      $connection,
      $query_placeholder,
      // MySQL's optimizer isn't able to work around the index obfuscation that
      // results from casting a numeric property.
      is_numeric($condition['value']) || $connection->isMariaDb()
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getJsonFieldFragmentFunction(string $field, string $jsonpath, mixed $value, Connection $connection): string {
    assert($connection instanceof MySqlConnection);
    $fragment = $this->doGetJsonFieldFragmentFunction($field, $jsonpath, $value, $connection);
    return is_numeric($value)
      // This would hide the index on MySQL, however we account for that in
      // ::getJsonFieldFragment().
      // @todo When MySQL 8.0.21+ is required, use JSON_VALUE().
      ? sprintf('CAST(%s AS DECIMAL)', $fragment)
      // MySQL/MariaDB will otherwise cast the result to an int, so be explicit.
      : $fragment . (is_bool($value) ? ' = true' : '');
  }

}
