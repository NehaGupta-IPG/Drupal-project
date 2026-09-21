<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query\DatabaseAbstract;

use Drupal\Core\Database\Expression;

/**
 * Database abstract query builder for SELECT statements.
 *
 * @phpstan-type TableEntry array{
 *     'join type':?string,
 *     'table':string|Select,
 *     'alias':string,
 *     'condition':string|Condition|null,
 *     'arguments':array,
 *     'all_fields'?:bool,
 *   }
 */
final class Select {

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
   * @var array<string,array{'field':string,'table':string,'alias':string}>
   */
  public private(set) array $columns = [];

  /**
   * The expressions to SELECT as virtual columns.
   *
   * @var array<string,Expression>
   */
  public private(set) array $expressions = [];

  /**
   * The tables against which to JOIN.
   *
   * @var array<string,TableEntry>
   * This property is a nested array. Each entry is an array representing
   * a single table against which to join. The structure of each entry is:
   *
   * @code
   * [
   *   'join type' => $join_type (one of INNER, LEFT OUTER, RIGHT OUTER),
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
   * @var array<string,string>
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
   * @var array
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
   * @var list<array{'type':string,'query':Select}>
   */
  public private(set) array $union = [];

  /**
   * The FOR UPDATE status.
   */
  public private(set) bool $forUpdate = FALSE;

  /**
   * The query metadata for alter purposes.
   */
  public private(set) array $alterMetadata = [];

  /**
   * The query tags.
   */
  public private(set) array $alterTags = [];

  /**
   * Constructs a Select object.
   *
   * @param string|\Drupal\Core\Database\Query\DatabaseAbstract\Select $table
   *   The table name or subquery that is being queried.
   * @param string $alias
   *   The alias for the table.
   * @param Conjunction $conjunction
   *   The default conjunction that will be applied to arrays of conditions.
   */
  public function __construct(
    string|Select $table,
    ?string $alias = NULL,
    public readonly Conjunction $conjunction = Conjunction::And,
  ) {
    $this->addJoin(NULL, $table, $alias);
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
   * {@inheritdoc}
   */
  public function addTag(string $tag): static {
    $this->alterTags[$tag] = 1;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addMetadata(string $key, mixed $object): static {
    $this->alterMetadata[$key] = $object;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function having(Expression $expression): static {
    if (!isset($this->having)) {
      $this->having = new Condition($this->conjunction);
    }
    $this->having->where($expression);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function havingIsNull(string|Condition $variable): static {
    if (!isset($this->having)) {
      $this->having = new Condition($this->conjunction);
    }
    $this->having->isNull($variable);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function havingIsNotNull(string|Condition $variable): static {
    if (!isset($this->having)) {
      $this->having = new Condition($this->conjunction);
    }
    $this->having->isNotNull($variable);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function havingExists(Select $select): static {
    if (!isset($this->having)) {
      $this->having = new Condition($this->conjunction);
    }
    $this->having->exists($select);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function havingNotExists(Select $select): static {
    if (!isset($this->having)) {
      $this->having = new Condition($this->conjunction);
    }
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
  public function distinct(bool $distinct = TRUE): static {
    $this->distinct = $distinct;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addColumn(string $table_alias, string $column, ?string $alias = NULL): static {
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

    return $this;
  }

  /**
   * Add multiple columns from the same table to be SELECTed.
   *
   * @param string $table_alias
   *   The name of the table from which the column comes, as an alias.
   *   Generally you will want to use the return value of join() here to ensure
   *   that it is valid.
   * @param list<string> $columns
   *   An indexed array of columns present in the specified table that should
   *   be included in this query. If not specified, $table_alias.* will be
   *   generated without any aliases.
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
   * Adds an expression to the list of "columns" to be SELECTed.
   *
   * @param string $expression
   *   The expression string. May contain placeholders.
   * @param string $alias
   *   The alias for this expression. If not specified, one will be generated
   *   automatically in the form "expression_#". The alias will be checked for
   *   uniqueness, so the requested alias may not be the alias that is assigned
   *   in all cases.
   * @param array<string,string|int|float|bool> $arguments
   *   Any placeholder arguments needed for this expression.
   */
  public function addExpression(string $expression, ?string $alias = NULL, array $arguments = []): static {
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

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function join(string|Select $table, ?string $alias = NULL, string|Condition|Expression|null $condition = NULL, array $arguments = []): static {
    return $this->addJoin('INNER', $table, $alias, $condition, $arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function innerJoin(string|Select $table, ?string $alias = NULL, string|Condition|Expression|null $condition = NULL, array $arguments = []): static {
    return $this->addJoin('INNER', $table, $alias, $condition, $arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function leftJoin(string|Select $table, ?string $alias = NULL, string|Condition|Expression|null $condition = NULL, array $arguments = []): static {
    return $this->addJoin('LEFT OUTER', $table, $alias, $condition, $arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function addJoin(?string $type, string|Select $table, ?string $alias = NULL, string|Condition|Expression|null $condition = NULL, array $arguments = []): static {
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

    if ($condition instanceof Expression) {
      $tmp = new Condition(Conjunction::And);
      $where = str_replace('%alias', $alias, $condition->expression);
      $tmp->where(new Expression($where, $condition->arguments));
      $condition = $tmp;
    }
    elseif (is_string($condition)) {
      $condition = str_replace('%alias', $alias, $condition);
    }

    $this->tables[$alias] = [
      'join type' => $type,
      'table' => $table,
      'alias' => $alias,
      'condition' => $condition,
      'arguments' => $arguments,
    ];

    return $this;
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
  public function orderRandom(string $alias): static {
    $this->addExpression('RAND()', $alias);
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
  private function prepareCountQuery(): Select {
    // Create our new query object that we will mutate into a count query.
    $count = clone($this);

    $group_by = $count->groupBy;
    $having = isset($this->having) ? $count->having?->conditions : NULL;

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

  /**
   * {@inheritdoc}
   */
  public function condition(string|Condition|Select $variable, string|int|bool|float|array|Select|null $value = NULL, ?string $operator = '='): static {
    if (!isset($this->condition)) {
      $this->condition = new Condition($this->conjunction);
    }
    $this->condition->condition($variable, $value, $operator);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isNull(string|Condition $variable): static {
    if (!isset($this->condition)) {
      $this->condition = new Condition($this->conjunction);
    }
    $this->condition->isNull($variable);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isNotNull(string|Condition $variable): static {
    if (!isset($this->condition)) {
      $this->condition = new Condition($this->conjunction);
    }
    $this->condition->isNotNull($variable);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function exists(Select $select): static {
    if (!isset($this->condition)) {
      $this->condition = new Condition($this->conjunction);
    }
    $this->condition->exists($select);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function notExists(Select $select): static {
    if (!isset($this->condition)) {
      $this->condition = new Condition($this->conjunction);
    }
    $this->condition->notExists($select);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function alwaysFalse(): static {
    if (!isset($this->condition)) {
      $this->condition = new Condition($this->conjunction);
    }
    $this->condition->alwaysFalse();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function where(Expression $expression): static {
    if (!isset($this->condition)) {
      $this->condition = new Condition($this->conjunction);
    }
    $this->condition->where($expression);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  private static function conditionGroupFactory(Conjunction $conjunction): Condition {
    return new Condition($conjunction);
  }

  /**
   * {@inheritdoc}
   */
  public static function andConditionGroup(): Condition {
    return static::conditionGroupFactory(Conjunction::And);
  }

  /**
   * {@inheritdoc}
   */
  public static function orConditionGroup(): Condition {
    return static::conditionGroupFactory(Conjunction::Or);
  }

  /**
   * {@inheritdoc}
   */
  public function __serialize(): array {
    if ($this->alterMetadata !== []) {
      throw new \LogicException(__CLASS__ . ' objects containing alter metadata cannot be serialized');
    }
    if ($this->alterTags !== []) {
      throw new \LogicException(__CLASS__ . ' objects containing alter tags cannot be serialized');
    }
    return get_object_vars($this);
  }

}
