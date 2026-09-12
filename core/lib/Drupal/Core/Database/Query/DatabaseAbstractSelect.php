<?php

namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\Expression;

/**
 * Database abstract query builder for SELECT statements.
 *
 * @ingroup database
 */
final class DatabaseAbstractSelect {

  use QueryConditionTrait;

  /**
   * The placeholder counter.
   */
  protected int $nextPlaceholder = 0;

  /**
   * An array of comments that can be prepended to a query.
   *
   * @var list<string>
   */
  protected array $comments = [];

  // ------------------------------------------------------

  /**
   * The fields to SELECT.
   *
   * @var list<string>
   */
  protected array $columns = [];

  /**
   * The expressions to SELECT as virtual fields.
   *
   * @var list<Expression>
   */
  protected array $expressions = [];

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
  protected array $tables = [];

  /**
   * The fields by which to order this query.
   *
   * This is an associative array. The keys are the fields to order, and the
   * value is the direction to order, either ASC or DESC.
   *
   * @var array<string,string>
   */
  protected array $order = [];

  /**
   * The fields by which to group.
   *
   * @var list<string>
   */
  protected array $group = [];

  /**
   * The conditional object for the HAVING clause.
   */
  protected DatabaseAbstractCondition $having;

  /**
   * Whether or not this query should be DISTINCT.
   */
  protected bool $distinct = FALSE;

  /**
   * The range limiters for this query.
   *
   * @var array|null
   */
  protected ?array $range;

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
  protected array $union = [];

  /**
   * The FOR UPDATE status.
   */
  protected bool $forUpdate = FALSE;

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

  /**
   * Returns a reference to the comments array for the query.
   *
   * Because this method returns by reference, alter hooks may edit the comments
   * array directly to make their changes. If just adding comments, however, the
   * use of comment() is preferred.
   *
   * Note that this method must be called by reference as well:
   * @code
   * $comments =& $query->getComments();
   * @endcode
   *
   * @return array
   *   A reference to the comments array structure.
   */
  public function &getComments(): array {
    return $this->comments;
  }

  // ------------------------------------------------------

  /**
   * Constructs a DatabaseAbstractSelect object.
   *
   * @param string|\Drupal\Core\Database\Query\DatabaseAbstractSelect $table
   *   The table name or subquery that is being queried.
   * @param string $alias
   *   The alias for the table.
   */
  public function __construct(
    string|DatabaseAbstractSelect $table,
    ?string $alias = NULL,
    string $conjunction = 'AND',
  ) {
    $this->condition = new DatabaseAbstractCondition($conjunction);
    $this->having = new DatabaseAbstractCondition($conjunction);
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
      if ($table['table'] instanceof DatabaseAbstractSelect) {
        $args += $table['table']->arguments();
      }
      // If the join condition is an object, grab its arguments recursively.
      if (!empty($table['condition']) && $table['condition'] instanceof DatabaseAbstractCondition) {
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
  public function havingCondition($field, $value = NULL, $operator = NULL) {
    $this->having->condition($field, $value, $operator);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function &havingConditions() {
    return $this->having->conditions();
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
  public function havingIsNull($field) {
    $this->having->isNull($field);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function havingIsNotNull($field) {
    $this->having->isNotNull($field);
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
  public function forUpdate($set = TRUE) {
    if (isset($set)) {
      $this->forUpdate = $set;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function &getColumns() {
    return $this->columns;
  }

  /**
   * {@inheritdoc}
   */
  public function &getExpressions() {
    return $this->expressions;
  }

  /**
   * {@inheritdoc}
   */
  public function &getOrderBy() {
    return $this->order;
  }

  /**
   * {@inheritdoc}
   */
  public function &getGroupBy() {
    return $this->group;
  }

  /**
   * {@inheritdoc}
   */
  public function &getTables() {
    return $this->tables;
  }

  /**
   * {@inheritdoc}
   */
  public function &getRange(): ?array {
    return $this->range;
  }

  /**
   * {@inheritdoc}
   */
  public function &getUnion() {
    return $this->union;
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
  public function distinct($distinct = TRUE) {
    $this->distinct = $distinct;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addColumn(string $table_alias, string $field, ?string $alias = NULL): string {
    // If no alias is specified, first try the field name itself.
    if (empty($alias)) {
      $alias = $field;
    }

    // If that's already in use, try the table name and field name.
    if (!empty($this->columns[$alias])) {
      $alias = $table_alias . '_' . $field;
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
      'field' => $field,
      'table' => $table_alias,
      'alias' => $alias,
    ];

    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  public function columns(string $table_alias, array $fields = []): static {
    if ($fields) {
      foreach ($fields as $field) {
        // We don't care what alias was assigned.
        $this->addColumn($table_alias, $field);
      }
    }
    else {
      // We want all fields from this table.
      $this->tables[$table_alias]['all_fields'] = TRUE;
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addExpression(string $expression, ?string $alias = NULL, array $arguments = []) {
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
  public function join($table, $alias = NULL, $condition = NULL, $arguments = []) {
    return $this->addJoin('INNER', $table, $alias, $condition, $arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function innerJoin($table, $alias = NULL, $condition = NULL, $arguments = []) {
    return $this->addJoin('INNER', $table, $alias, $condition, $arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function leftJoin($table, $alias = NULL, $condition = NULL, $arguments = []) {
    return $this->addJoin('LEFT OUTER', $table, $alias, $condition, $arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function addJoin($type, $table, $alias = NULL, $condition = NULL, $arguments = []) {
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
  public function orderBy($field, $direction = 'ASC') {
    // Only allow ASC and DESC, default to ASC.
    $direction = strtoupper($direction) == 'DESC' ? 'DESC' : 'ASC';
    $this->order[$field] = $direction;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function orderRandom() {
    $alias = $this->addExpression('RAND()', 'random_field');
    $this->orderBy($alias);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function range($start = NULL, $length = NULL) {
    $this->range = $start !== NULL ? ['start' => $start, 'length' => $length] : [];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function union(DatabaseAbstractSelect $query, string $type = '') {
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
  public function groupBy($field) {
    $this->group[$field] = $field;
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
   * @return \Drupal\Core\Database\Query\Select
   *   A new query object ready to have COUNT(*) performed on it.
   */
  protected function prepareCountQuery() {
    // Create our new query object that we will mutate into a count query.
    $count = clone($this);

    $group_by = $count->getGroupBy();
    $having = $count->havingConditions();

    if (!$count->distinct && !isset($having[0])) {
      // When not executing a distinct query, we can zero-out existing fields
      // and expressions that are not used by a GROUP BY or HAVING. Fields
      // listed in a GROUP BY or HAVING clause need to be present in the
      // query.
      $fields =& $count->getColumns();
      foreach (array_keys($fields) as $field) {
        if (empty($group_by[$field])) {
          unset($fields[$field]);
        }
      }

      $expressions =& $count->getExpressions();
      foreach (array_keys($expressions) as $field) {
        if (empty($group_by[$field])) {
          unset($expressions[$field]);
        }
      }

      // Also remove 'all_fields' statements, which are expanded into
      // tablename.* when the query is executed.
      foreach ($count->tables as &$table) {
        unset($table['all_fields']);
      }
    }

    // If we've just removed all fields from the query, make sure there is at
    // least one so that the query still runs.
    $count->addExpression('1');

    // Ordering a count query is a waste of cycles, and breaks on some
    // databases anyway.
    $orders = &$count->getOrderBy();
    $orders = [];

    if ($count->distinct && !empty($group_by)) {
      // If the query is distinct and contains a GROUP BY, we need to remove the
      // distinct because SQL99 does not support counting on distinct multiple
      // fields.
      $count->distinct = FALSE;
    }

    // If there are any dependent queries to UNION, prepare each of those for
    // the count query also.
    foreach ($count->union as &$union) {
      $union['query'] = $union['query']->prepareCountQuery();
    }

    return $count;
  }

}
