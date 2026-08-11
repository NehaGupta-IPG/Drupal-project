<?php

namespace Drupal\views\Plugin\views\query;

/**
 * Defines an interface for defining cast expressions in SQL.
 */
interface CastSqlInterface {

  /**
   * Returns a database expression to cast the field to int.
   *
   * @param string $field
   *   The database field to cast.
   *
   * @return string
   *   The database expression to cast the field to int.
   */
  public function getFieldAsInt(string $field): string;

  /**
   * Returns two operands in a form the database is able to compare.
   *
   * Database engines differ in how strict they are about the types of the
   * values they compare. MySQL and SQLite convert a string operand to a number
   * when it is compared with a number, while PostgreSQL has no operator for
   * such a comparison and fails the query instead. Entity IDs are stored as
   * integers by most entity types and as strings by others, and tables that
   * track entities of any type, such as {file_usage}, store all of them as
   * strings. Comparing the two therefore has to be prepared per engine.
   *
   * @param array $left
   *   An array describing the left operand, with these keys:
   *   - expression: The SQL expression of the operand.
   *   - table: The unprefixed name of the table the operand belongs to, or NULL
   *     if the expression is not a plain column.
   *   - column: The name of the column the operand refers to, or NULL if the
   *     expression is not a plain column.
   * @param array $right
   *   An array describing the right operand, in the format of $left.
   *
   * @return string[]
   *   The left and the right SQL expression, in that order.
   */
  public function getComparableOperands(array $left, array $right): array;

}
