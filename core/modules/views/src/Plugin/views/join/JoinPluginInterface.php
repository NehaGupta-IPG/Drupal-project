<?php

namespace Drupal\views\Plugin\views\join;

/**
 * Represents a join and creates the SQL necessary to implement the join.
 *
 * Extensions of this class can be used to create more interesting joins.
 */
interface JoinPluginInterface {

  /**
   * Builds the SQL for the join this object represents.
   *
   * When possible, try to use table alias instead of table names.
   * Implementations typically call `$select_query->addJoin()` one or more
   * times to append the actual SQL join(s) to the in-progress Views query.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $select_query
   *   The in-progress SELECT query for the View. Use this object to add joins
   *   and any join condition arguments.
   * @param array $table
   *   The table metadata array for the table being joined. In SQL Views
   *   queries, this comes from the table queue entry created by
   *   \Drupal\views\Plugin\views\query\Sql::queueTable() (the method returns
   *   the alias string, but also stores this metadata internally). For the SQL
   *   implementation, that table queue entry contains these keys:
   *   - table: The real table name.
   *   - num: Incremental occurrence index for this table.
   *   - alias: The SQL alias to use in join conditions.
   *   - join: The join plugin instance that produced this table entry.
   *   - relationship: The relationship alias/base context for this table.
   *   Non-SQL query plugins may provide a different structure.
   * @param \Drupal\views\Plugin\views\query\QueryPluginBase $view_query
   *   The source Views query plugin.
   */
  public function buildJoin($select_query, $table, $view_query);

}
