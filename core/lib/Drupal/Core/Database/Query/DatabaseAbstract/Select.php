<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query\DatabaseAbstract;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\Expression;

/**
 * Database abstract query builder for SELECT statements.
 *
 * @ingroup database
 */
final class Select {

  /**
   * The placeholder counter.
   */
  protected int $nextPlaceholder = 0;

  /**
   * An array of comments that can be prepended to a query.
   *
   * @var list<string>
   */
  public private(set) array $comments = [];

  /**
   * The condition object for this query.
   *
   * Condition handling is handled via composition.
   */
  public private(set) ?Condition $condition = NULL;

  /**
   * The columns to SELECT.
   *
   * @var list<string>
   */
  public private(set) array $columns = [];

  /**
   * The expressions to SELECT as virtual columns.
   *
   * @var list<Expression>
   */
  public private(set) array $expressions = [];

  /**
   * The tables against which to JOIN.
   *
   * @var array
   * This property is a nested array. Each entry is an array representing
   * a single table against which to join. The structure of each entry is:
   *
   * @code
   * [
   *   'type' => $join_type (one of INNER, LEFT OUTER, RIGHT OUTER),
   *   'table' => $table,
   *   'alias' => $alias_of_the_table,
   *   'condition' => $join_condition (string or Condition object),
   *   'arguments' => $array_of_arguments_for_placeholders_in_the condition.
   *   'all_fields' => TRUE to SELECT $alias.*, FALSE or NULL otherwise.
   * ]
   * @endcode
   * If $table is a string, it is taken as the name of a table. If it is
   * a Select query object, it is taken as a subquery.
   *
   * If $join_condition is a Condition object, any arguments should be
   * incorporated into the object; a separate array of arguments does not
   * need to be provided.
   */
  public private(set) array $tables = [];

  /**
   * The columns by which to order this query.
   *
   * This is an associative array. The keys are the columns to order, and the
   * value is the direction to order, either ASC or DESC.
   *
   * @var array<string,string>
   */
  public private(set) array $orderBy = [];

  /**
   * The columns by which to group.
   *
   * @var list<string>
   */
  public private(set) array $groupBy = [];

  /**
   * The conditional object for the HAVING clause.
   */
  public private(set) ?Condition $having = NULL;

  /**
   * Whether or not this query should be DISTINCT.
   */
  public private(set) bool $distinct = FALSE;

  /**
   * The range limiters for this query.
   *
   * @var array|null
   */
  public private(set) array $range = [];

  /**
   * An array whose elements specify a query to UNION, and the UNION type.
   *
   * The 'type' key may be '', 'ALL', or 'DISTINCT' to represent a 'UNION',
   * 'UNION ALL', or 'UNION DISTINCT' statement, respectively.
   *
   * All entries in this array will be applied from front to back, with the
   * first query to union on the right of the original query, the second union
   * to the right of the first, etc.
   *
   * @var array
   */
  public private(set) array $union = [];

  /**
   * The FOR UPDATE status.
   */
  public private(set) bool $forUpdate = FALSE;

  // ------------------------------------------------------
  // ------------------------------------------------------

  /**
   * Gets the next placeholder value for this query object.
   *
   * @return int
   *   The next placeholder value.
   */
  public function nextPlaceholder(): int {
    return $this->nextPlaceholder++;
  }

  /**
   * Adds a comment to the query.
   *
   * By adding a comment to a query, you can more easily find it in your
   * query log or the list of active queries on an SQL server. This allows
   * for easier debugging and allows you to more easily find where a query
   * with a performance problem is being generated.
   *
   * The comment string will be sanitized to remove * / and other characters
   * that may terminate the string early so as to avoid SQL injection attacks.
   *
   * @param string $comment
   *   The comment string to be inserted into the query.
   *
   * @return $this
   */
  public function comment(string $comment): static {
    $this->comments[] = $comment;
    return $this;
  }

  // ------------------------------------------------------

  /**
   * Constructs a Select object.
   *
   * @param string|\Drupal\Core\Database\Query\DatabaseAbstract\Select $table
   *   The table name or subquery that is being queried.
   * @param string $alias
   *   The alias for the table.
   */
  public function __construct(
    string|Select $table,
    ?string $alias = NULL,
    public readonly string $conjunction = 'AND',
  ) {
    $this->addJoin(NULL, $table, $alias);
  }

  /**
   * {@inheritdoc}
   */
  public function arguments() {
    if (!$this->compiled()) {
      return NULL;
    }

    $args = $this->condition->arguments() + $this->having->arguments();

    foreach ($this->tables as $table) {
      if ($table['arguments']) {
        $args += $table['arguments'];
      }
      // If this table is a subquery, grab its arguments recursively.
      if ($table['table'] instanceof Select) {
        $args += $table['table']->arguments();
      }
      // If the join condition is an object, grab its arguments recursively.
      if (!empty($table['condition']) && $table['condition'] instanceof Condition) {
        $args += $table['condition']->arguments();
      }
    }

    foreach ($this->expressions as $expression) {
      if ($expression['arguments']) {
        $args += $expression['arguments'];
      }
    }

    // If there are any dependent queries to UNION,
    // incorporate their arguments recursively.
    foreach ($this->union as $union) {
      $args += $union['query']->arguments();
    }

    return $args;
  }

  /**
   * {@inheritdoc}
   */
  public function havingCondition($column, $value = NULL, $operator = NULL) {
    $this->having->condition($column, $value, $operator);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function havingArguments() {
    return $this->having->arguments();
  }

  /**
   * {@inheritdoc}
   */
  public function having($snippet, $args = []) {
    if (!isset($this->having)) {
      $this->having = new Condition($this->conjunction);
    }
    $this->having->where($snippet, $args);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function havingCompile(Connection $connection) {
    $this->having->compile($connection, $this);
  }

  /**
   * {@inheritdoc}
   */
  public function havingIsNull($column) {
    $this->having->isNull($column);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function havingIsNotNull($column) {
    $this->having->isNotNull($column);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function havingExists(SelectInterface $select) {
    $this->having->exists($select);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function havingNotExists(SelectInterface $select) {
    $this->having->notExists($select);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function forUpdate(bool $set = TRUE): static {
    $this->forUpdate = $set;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function resetOrderBy(): static {
    $this->orderBy = [];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function escapeLike($string) {
    return $this->connection->escapeLike($string);
  }

  /**
   * {@inheritdoc}
   */
  public function escapeField($string) {
    return $this->connection->escapeField($string);
  }

  /**
   * {@inheritdoc}
   */
  public function getArguments(?PlaceholderInterface $queryPlaceholder = NULL) {
    if (!isset($queryPlaceholder)) {
      $queryPlaceholder = $this;
    }
    $this->compile($this->connection, $queryPlaceholder);
    return $this->arguments();
  }

  /**
   * {@inheritdoc}
   */
  public function distinct(bool $distinct = TRUE): static {
    $this->distinct = $distinct;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addColumn(string $table_alias, string $column, ?string $alias = NULL): string {
    // If no alias is specified, first try the column name itself.
    if (empty($alias)) {
      $alias = $column;
    }

    // If that's already in use, try the table name and column name.
    if (!empty($this->columns[$alias])) {
      $alias = $table_alias . '_' . $column;
    }

    // If that is already used, just add a counter until we find an unused
    // alias.
    $alias_candidate = $alias;
    $count = 2;
    while (!empty($this->columns[$alias_candidate])) {
      $alias_candidate = $alias . '_' . $count++;
    }
    $alias = $alias_candidate;

    $this->columns[$alias] = [
      'field' => $column,
      'table' => $table_alias,
      'alias' => $alias,
    ];

    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  public function columns(string $table_alias, array $columns = []): static {
    if ($columns) {
      foreach ($columns as $column) {
        // We don't care what alias was assigned.
        $this->addColumn($table_alias, $column);
      }
    }
    else {
      // We want all columns from this table.
      $this->tables[$table_alias]['all_fields'] = TRUE;
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addExpression(string $expression, ?string $alias = NULL, array $arguments = []): string {
    if (empty($alias)) {
      $alias = 'expression';
    }

    $alias_candidate = $alias;
    $count = 2;
    while (!empty($this->expressions[$alias_candidate])) {
      $alias_candidate = $alias . '_' . $count++;
    }
    $alias = $alias_candidate;

    $this->expressions[$alias] = new Expression($expression, $arguments, $alias);

    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  public function join(string|Select $table, ?string $alias = NULL, ?string $condition = NULL, array $arguments = []): string {
    return $this->addJoin('INNER', $table, $alias, $condition, $arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function innerJoin(string|Select $table, ?string $alias = NULL, ?string $condition = NULL, array $arguments = []): string {
    return $this->addJoin('INNER', $table, $alias, $condition, $arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function leftJoin(string|Select $table, ?string $alias = NULL, ?string $condition = NULL, array $arguments = []): string {
    return $this->addJoin('LEFT OUTER', $table, $alias, $condition, $arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function addJoin(?string $type, string|Select $table, ?string $alias = NULL, ?string $condition = NULL, array $arguments = []): string {
    if (empty($alias)) {
      if ($table instanceof self) {
        $alias = 'subquery';
      }
      else {
        $alias = $table;
      }
    }

    $alias_candidate = $alias;
    $count = 2;
    while (!empty($this->tables[$alias_candidate])) {
      $alias_candidate = $alias . '_' . $count++;
    }
    $alias = $alias_candidate;

    if (is_string($condition)) {
      $condition = str_replace('%alias', $alias, $condition);
    }

    $this->tables[$alias] = [
      'join type' => $type,
      'table' => $table,
      'alias' => $alias,
      'condition' => $condition,
      'arguments' => $arguments,
    ];

    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  public function orderBy(string $column, string $direction = 'ASC'): static {
    // Only allow ASC and DESC, default to ASC.
    $direction = strtoupper($direction) == 'DESC' ? 'DESC' : 'ASC';
    $this->orderBy[$column] = $direction;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function orderRandom(): static {
    $alias = $this->addExpression('RAND()', 'random_field');
    $this->orderBy($alias);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function range(?int $start = NULL, ?int $length = NULL): static {
    $this->range = $start !== NULL ? ['start' => $start, 'length' => $length] : [];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function union(Select $query, string $type = ''): static {
    // Handle UNION aliasing.
    switch ($type) {
      // Fold UNION DISTINCT to UNION for better cross database support.
      case 'DISTINCT':
      case '':
        $type = 'UNION';
        break;

      case 'ALL':
        $type = 'UNION ALL';
      default:
    }

    $this->union[] = [
      'type' => $type,
      'query' => $query,
    ];

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function groupBy(string $column): static {
    $this->groupBy[$column] = $column;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function countQuery(): static {
    $count = $this->prepareCountQuery();

    $query = new self($count);
    $query->addExpression('COUNT(*)');

    return $query;
  }

  /**
   * Prepares a count query from the current query object.
   *
   * @return Select
   *   A new query object ready to have COUNT(*) performed on it.
   */
  protected function prepareCountQuery(): Select {
    // Create our new query object that we will mutate into a count query.
    $count = clone($this);

    $group_by = $count->groupBy;
    $having = isset($this->having) ? $count->havingConditions() : NULL;

    if (!$count->distinct && !isset($having[0])) {
      // When not executing a distinct query, we can zero-out existing columns
      // and expressions that are not used by a GROUP BY or HAVING. columns
      // listed in a GROUP BY or HAVING clause need to be present in the
      // query.
      foreach (array_keys($count->columns) as $column) {
        if (empty($count->groupBy[$column])) {
          unset($count->columns[$column]);
        }
      }
      foreach (array_keys($count->expressions) as $column) {
        if (empty($count->groupBy[$column])) {
          unset($count->expressions[$column]);
        }
      }

      // Also remove 'all_fields' statements, which are expanded into
      // tablename.* when the query is executed.
      foreach ($count->tables as &$table) {
        unset($table['all_fields']);
      }
    }

    // If we've just removed all columns from the query, make sure there is at
    // least one so that the query still runs.
    $count->addExpression('1');

    // Ordering a count query is a waste of cycles, and breaks on some
    // databases anyway.
    $count->resetOrderBy();

    if ($count->distinct && !empty($group_by)) {
      // If the query is distinct and contains a GROUP BY, we need to remove the
      // distinct because SQL99 does not support counting on distinct multiple
      // columns.
      $count->distinct = FALSE;
    }

    // If there are any dependent queries to UNION, prepare each of those for
    // the count query also.
    foreach ($count->union as &$union) {
      $union['query'] = $union['query']->prepareCountQuery();
    }

    return $count;
  }

  // ------------------------------------------------------

  /**
   * {@inheritdoc}
   */
  public function condition($column, $value = NULL, $operator = '=') {
    if (!isset($this->condition)) {
      $this->condition = new Condition($this->conjunction);
    }
    $this->condition->condition($column, $value, $operator);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isNull($column) {
    if (!isset($this->condition)) {
      $this->condition = new Condition($this->conjunction);
    }
    $this->condition->isNull($column);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isNotNull($column) {
    if (!isset($this->condition)) {
      $this->condition = new Condition($this->conjunction);
    }
    $this->condition->isNotNull($column);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function exists(SelectInterface $select) {
    $this->condition->exists($select);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function notExists(SelectInterface $select) {
    $this->condition->notExists($select);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function alwaysFalse() {
    if (!isset($this->condition)) {
      $this->condition = new Condition($this->conjunction);
    }
    $this->condition->alwaysFalse();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function conditionArguments() {
    if (!isset($this->condition)) {
      $this->condition = new Condition($this->conjunction);
    }
    return $this->condition->arguments();
  }

  /**
   * {@inheritdoc}
   */
  public function where($snippet, $args = []) {
    if (!isset($this->condition)) {
      $this->condition = new Condition($this->conjunction);
    }
    $this->condition->where($snippet, $args);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function compile(Connection $connection, PlaceholderInterface $queryPlaceholder) {
    $this->condition->compile($connection, $queryPlaceholder);
  }

  /**
   * {@inheritdoc}
   */
  public function compiled() {
    return $this->condition->compiled();
  }

  /**
   * {@inheritdoc}
   */
  public function conditionGroupFactory($conjunction = 'AND') {
    return $this->connection->condition($conjunction);
  }

  /**
   * {@inheritdoc}
   */
  public function andConditionGroup() {
    return $this->conditionGroupFactory('AND');
  }

  /**
   * {@inheritdoc}
   */
  public function orConditionGroup() {
    return $this->conditionGroupFactory('OR');
  }

}
