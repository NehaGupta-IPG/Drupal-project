<?php

declare(strict_types=1);

namespace Drupal\views\Plugin\views\query;

/**
 * Defines common query operations used by Views handlers.
 *
 * These operations can be implemented by SQL and non-SQL query backends.
 */
interface HandlerQueryInterface {

  /**
   * Adds a field to the query.
   *
   * @param string|null $table
   *   The table or backend-specific field namespace.
   * @param string $field
   *   The field to add.
   * @param string $alias
   *   An optional alias.
   * @param array $params
   *   Optional backend-specific parameters.
   *
   * @return string
   *   The field alias.
   */
  public function addField($table, $field, $alias = '', array $params = []);

  /**
   * Adds a condition to the query.
   *
   * @param int|string|null $group
   *   The condition group.
   * @param mixed $field
   *   The field or backend-supported condition object.
   * @param mixed $value
   *   The value to compare.
   * @param string|null $operator
   *   The comparison operator.
   *
   * @return mixed
   *   The backend-specific return value.
   */
  public function addWhere($group, $field, $value = NULL, $operator = NULL);

  /**
   * Adds ordering to the query.
   *
   * @param string|null $table
   *   The table or backend-specific sort namespace.
   * @param string|null $field
   *   The field to sort on.
   * @param string $order
   *   The sort direction.
   * @param string $alias
   *   An optional field alias.
   * @param array $params
   *   Optional backend-specific parameters.
   *
   * @return mixed
   *   The backend-specific return value.
   */
  public function addOrderBy($table, $field = NULL, $order = 'ASC', $alias = '', array $params = []);

}
