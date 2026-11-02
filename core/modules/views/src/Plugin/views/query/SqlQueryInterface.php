<?php

declare(strict_types=1);

namespace Drupal\views\Plugin\views\query;

use Drupal\views\Plugin\views\join\JoinPluginBase;

/**
 * Defines SQL-specific query operations used by Views handlers.
 */
interface SqlQueryInterface extends HandlerQueryInterface {

  /**
   * Sets the field used when counting query results.
   *
   * @return mixed
   *   The backend-specific return value.
   */
  public function setCountField($table, $field, $alias = NULL);

  /**
   * Removes fields previously added to the query.
   *
   * @return mixed
   *   The backend-specific return value.
   */
  public function clearFields();

  /**
   * Adds grouping to the query.
   *
   * @return mixed
   *   The backend-specific return value.
   */
  public function addGroupBy($clause);

  /**
   * Adds a SQL WHERE expression to the query.
   *
   * @return mixed
   *   The backend-specific return value.
   */
  public function addWhereExpression($group, $snippet, $args = []);

  /**
   * Adds a SQL HAVING expression to the query.
   *
   * @return mixed
   *   The backend-specific return value.
   */
  public function addHavingExpression($group, $snippet, $args = []);

  /**
   * Ensures a SQL table is available to the query.
   *
   * @return mixed
   *   The backend-specific table alias or failure value.
   */
  public function ensureTable($table, $relationship = NULL, ?JoinPluginBase $join = NULL);

  /**
   * Adds a SQL table to the query.
   *
   * @return mixed
   *   The backend-specific table alias or failure value.
   */
  public function addTable($table, $relationship = NULL, ?JoinPluginBase $join = NULL, $alias = NULL);

  /**
   * Adds a SQL relationship to the query.
   *
   * @return mixed
   *   The backend-specific relationship alias or failure value.
   */
  public function addRelationship($alias, JoinPluginBase $join, $base, $link_point = NULL);

  /**
   * Generates a SQL placeholder.
   *
   * @return mixed
   *   The generated placeholder.
   */
  public function placeholder($base = 'views');

  /**
   * Adds a database query tag.
   *
   * @return mixed
   *   The backend-specific return value.
   */
  public function addTag($tag);

  /**
   * Gets the database connection used by the query.
   *
   * @return mixed
   *   The database connection.
   */
  public function getConnection();

}
