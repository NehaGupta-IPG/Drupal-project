<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\Connection as DatabaseConnection;
use Drupal\Core\Database\Event\StatementExecutionEndEvent;
use Drupal\Core\Database\Event\StatementExecutionStartEvent;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Database\Query\PlaceholderInterface;
use Drupal\Core\Database\Query\Select as QuerySelect;
use Drupal\Core\Database\Query\SelectInterface;

// cspell:ignore substringed

/**
 * The MongoDB implementation of \Drupal\Core\Database\Query\Select.
 */
class Select extends QuerySelect {

  /**
   * The array containing the filter part of the query.
   *
   * @var array
   */
  protected $mongodbFilter;

  /**
   * The array containing the filter part of the aggregate query.
   *
   * @var array
   */
  protected $mongodbAggregateFilter;

  /**
   * The array containing the projection part of the query.
   *
   * @var array
   */
  protected $mongodbProjection;

  /**
   * The array containing the sorting part of the query.
   *
   * @var array
   */
  protected $mongodbSort = [];

  /**
   * The value if the sorting part of the query can be done in one stage.
   *
   * @var bool
   */
  protected $mongodbSortSeparate = FALSE;

  /**
   * The value with how many results the query as a maximum should return.
   *
   * @var int
   */
  protected $mongodbLimit;

  /**
   * The integer value with how many results the query should skip.
   *
   * @var int
   */
  protected $mongodbSkip;

  /**
   * The base table alias used in the query.
   *
   * @var string
   */
  protected $mongodbBaseAlias;

  /**
   * The base table name used in the query.
   *
   * @var string
   */
  protected $mongodbBaseTable;

  /**
   * The array containing the lookups/joins part of the query.
   *
   * @var array
   */
  protected array $mongodbLookups = [];

  /**
   * The array containing the group by part of the query.
   *
   * @var array
   */
  protected array $mongodbGroup = [];

  /**
   * The array containing the group by fields of the query.
   *
   * @var array
   */
  protected array $mongodbGroupFields = [];

  /**
   * The array containing the fields to added before the joins are executed.
   *
   * @var array
   */
  protected array $mongodbAddFieldsPreJoin = [];

  /**
   * The list the fields to be added to $this->mongodbAddFieldsPreJoin.
   *
   * @var array
   */
  protected array $mongodbPreJoinFields = [];

  /**
   * The array containing the fields to added in the query.
   *
   * @var array
   */
  protected array $mongodbAddFields = [];

  /**
   * The array containing the fields to added after all other query parts.
   *
   * @var array
   */
  protected array $mongodbAddFieldsLast = [];

  /**
   * The array containing the fields to added literally to the query.
   *
   * @var array
   */
  protected array $mongodbLiteralFields = [];

  /**
   * The array containing the fields to added for conditions to the query.
   *
   * @var array
   */
  protected array $mongodbConditionFields = [];

  /**
   * The array containing the fields to added with their multiply value.
   *
   * @var array
   */
  protected array $mongodbMultiplyFields = [];

  /**
   * The array containing the fields to added to each other to the query.
   *
   * @var array
   */
  protected array $mongodbSumFields = [];

  /**
   * The array containing the fields to be concatenated to the query.
   *
   * @var array
   */
  protected array $mongodbConcatFields = [];

  /**
   * The array containing the fields to be substringed to the query.
   *
   * @var array
   */
  protected array $mongodbSubstringFields = [];

  /**
   * The array containing the fields to be multiplied and summed to the query.
   *
   * @var array
   */
  protected array $mongodbSumMultiplyFields = [];

  /**
   * The array containing the fields with their length to be added to the query.
   *
   * @var array
   */
  protected array $mongodbFieldsLength = [];

  /**
   * Array containing the date fields as a date string be added to the query.
   *
   * @var array
   */
  protected array $mongodbDateDateFormattedFields = [];

  /**
   * Array containing the string fields as a date string be added to the query.
   *
   * @var array
   */
  protected array $mongodbDateStringFormattedFields = [];

  /**
   * The array containing the field be added to the query.
   *
   * If they are null then be replaced by the given value.
   *
   * @var array
   */
  protected array $mongodbCoalesceValueFields = [];

  /**
   * The array containing the fields be added to the query.
   *
   * @var array
   */
  protected array $mongodbCoalesceFields = [];

  /**
   * The array containing the fields where the one with the greatest value.
   *
   * @var array
   */
  protected array $mongodbGreatestFields = [];

  /**
   * The list of embedded tables that need to be unwound in the aggregate query.
   *
   * @var array
   */
  protected array $mongodbUnwind = [];

  /**
   * The array containing the paths of embedded tables.
   *
   * That need to be unwound before the condition part of the query.
   *
   * @var array
   */
  protected array $mongodbFilterUnwindPaths = [];

  /**
   * The array containing the paths of embedded tables.
   *
   * That need to be unwound before the lookup/join part of the query.
   *
   * @var array
   */
  protected array $mongodbLookupUnwindPaths = [];

  /**
   * The list of embedded tables that have been unwound in the aggregate query.
   *
   * @var array
   */
  protected array $mongodbUnwoundPaths = [];

  /**
   * Exclude the default MongoDB field "_id" from the query result.
   *
   * @var bool
   */
  protected $mongodbRemoveIdField = TRUE;

  /**
   * Execute the query should be executed as an aggregate query.
   *
   * @var bool
   */
  protected $mongodbUseAggregate = FALSE;

  /**
   * Execute the query should be executed as a count query.
   *
   * @var bool
   */
  protected $mongodbCountQuery = FALSE;

  /**
   * Boolean value indicating that the query result should be in a random order.
   *
   * @var bool
   */
  protected $mongodbRandomOrder = FALSE;

  /**
   * The array containing the group by operation for the query.
   *
   * @var array
   */
  protected $mongodbGroupByOperation = [];

  /**
   * The name of the temporary table that will hold the result of the query.
   *
   * @var string
   */
  protected $mongodbTemporaryTable;

  /**
   * The embedded table that should be used as the base table for the query.
   *
   * @var string
   */
  protected $mongodbEmbeddedTableToUseAsBaseTable;

  /**
   * An array of condition for an aggregate query with group by operation.
   *
   * @var array
   */
  protected $mongodbHavingFilter;

  /**
   * An array of holding the joins to be unwound and the fields to add.
   *
   * @var array
   */
  protected $mongodbUnwindJoinAndAddFields = [];

  /**
   * Return the string value of the query.
   *
   * @var bool
   */
  protected $mongodbQueryStringValue = FALSE;

  /**
   * The table aliases for with all fields must be selected.
   *
   * This array is only used to fake the SQL "all_fields" variable, because it
   * is not used by MongoDB.
   *
   * @var array
   *   Array of table aliases.
   */
  protected $mongodbAllFieldsTables = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(DatabaseConnection $connection, $table, $alias = NULL, $options = []) {
    $this->uniqueIdentifier = uniqid('', TRUE);
    $this->connection = $connection;
    $this->connectionKey = $this->connection->getKey();
    $this->connectionTarget = $this->connection->getTarget();
    $this->queryOptions = $options;

    $conjunction = $options['conjunction'] ?? 'AND';
    $this->condition = $this->connection->condition($conjunction);
    $this->having = $this->connection->condition($conjunction);
    parent::addJoin(NULL, $table, $alias);

    $this->mongodbBaseTable = $connection->escapeTable($table);
    $this->mongodbBaseAlias = (!empty($alias) ? $connection->escapeTable($alias) : $connection->escapeTable($table));
  }

  /**
   * {@inheritdoc}
   */
  public function conditionGroupFactory($conjunction = 'AND') {
    // We need to use \Drupal\mongodb\Driver\Database\mongodb\Connection.
    return $this->connection->condition($conjunction);
  }

  /**
   * {@inheritdoc}
   */
  public function compile(DatabaseConnection $connection, PlaceholderInterface $queryPlaceholder) {
    $this->condition->setMongodbBaseTable($this->mongodbBaseTable);
    $this->condition->setMongodbBaseAlias($this->mongodbBaseAlias);
    if (!empty($this->alterMetaData)) {
      $this->condition->setMetaData($this->alterMetaData);
    }

    parent::compile($connection, $queryPlaceholder);
  }

  /**
   * Set the embedded table that should be used as the base table for the query.
   *
   * @param string $embedded_table
   *   The name of the embedded table to be used as the base table.
   *
   * @return $this
   *   The select query.
   */
  public function embeddedTableToUseAsBaseTable($embedded_table): self {
    $this->mongodbEmbeddedTableToUseAsBaseTable = $embedded_table;

    return $this;
  }

  /**
   * Add a group by operation to the query.
   *
   * @param string $alias
   *   The alias to be used to store the result on the operation in.
   * @param string $field
   *   The name of the field that is used.
   * @param string $operator
   *   The group by operation that used on the field.
   */
  public function addGroupByOperation($alias, $field, $operator) {
    if (!empty($alias) && !empty($field) && !empty($operator)) {
      $first_dot_position = strpos($field, '.');
      if ($first_dot_position !== FALSE) {
        $first_field_part = substr($field, 0, $first_dot_position);
        if (in_array($first_field_part, [$this->mongodbBaseTable, $this->mongodbBaseAlias])) {
          $field = substr($field, strlen($first_field_part) + 1);
        }
      }

      $this->mongodbGroupByOperation[$alias] = [
        'field' => $field,
        'alias' => $alias,
        'operator' => $operator,
      ];
    }

    return $alias;
  }

  /**
   * Add a field to group by to the query.
   *
   * @param string $alias
   *   The alias to be used to store the result in.
   * @param string $field
   *   The name of the field that to group by.
   */
  public function addGroupField($alias, $field) {
    if (!empty($alias) && !empty($field)) {
      $this->mongodbGroupFields[$alias] = $field;
    }
  }

  /**
   * Add a field with a literal value to the query.
   *
   * @param string $alias
   *   The alias to be used to store the result in.
   * @param string $value
   *   The literal value to assign the alias in the query result.
   */
  public function addLiteralField($alias, $value) {
    if (!empty($alias)) {
      $this->mongodbLiteralFields[$alias] = $value;
    }
  }

  /**
   * Add a field to the query that can be used by a condition.
   *
   * @param string $alias
   *   The alias to be used to store the result in.
   * @param string $field
   *   The name of the field hows value is assigned to the alias.
   */
  public function addConditionField($alias, $field) {
    if (!empty($alias) && !empty($field)) {
      $this->mongodbConditionFields[$alias] = $field;
    }
  }

  /**
   * Add a field to the query that holds the result of a multiplication.
   *
   * @param string $alias
   *   The alias to be used to store the result in.
   * @param string $field
   *   The name of the field as the basis for the multiplication.
   * @param string $value
   *   The value for the multiplication.
   */
  public function addMultiplyField($alias, $field, $value) {
    if (!empty($alias) && !empty($field) && !empty($value)) {
      $this->mongodbMultiplyFields[$alias] = [
        'alias' => $alias,
        'field' => $field,
        'value' => $value,
      ];
    }
  }

  /**
   * Add a field to the query that holds the result of a sum.
   *
   * @param string $alias
   *   The alias to be used to store the result in.
   * @param array $fields
   *   The list of fields for the sum.
   * @param string $value
   *   The value to add to sum.
   */
  public function addSumField($alias, $fields, $value = NULL) {
    if (!empty($alias) && !empty($fields)) {
      if (!is_array($fields)) {
        $fields = (array) $fields;
      }
      $this->mongodbSumFields[$alias] = [
        'fields' => $fields,
        'alias' => $alias,
        'value' => $value,
      ];
    }
  }

  /**
   * Add a field to the query that holds the result of the concatenation.
   *
   * @param string $alias
   *   The alias to be used to store the result in.
   * @param array $fields
   *   The list of fields for the concatenation.
   * @param array $integer_fields
   *   The list integer fields for the concatenation.
   */
  public function addConcatField($alias, array $fields = [], array $integer_fields = []) {
    if (!empty($alias) && !empty($fields)) {
      $this->mongodbConcatFields[$alias] = [
        'fields' => $fields,
        'alias' => $alias,
        'integer fields' => $integer_fields,
      ];
    }
  }

  /**
   * Add a field to the query that holds the result of substring value.
   *
   * @param string $alias
   *   The alias to be used to store the result in.
   * @param string $field
   *   The name of the field for substring value.
   * @param int $start
   *   The start position for the substring value.
   * @param int $length
   *   The length value for the substring.
   */
  public function addSubstringField($alias, $field, $start, $length) {
    if (!empty($alias) && !empty($field)) {
      $this->mongodbSubstringFields[$alias] = [
        'field' => $field,
        'alias' => $alias,
        'start' => $start,
        'length' => $length,
      ];
    }
  }

  /**
   * Add a field to the query that holds the result of the field length.
   *
   * @param string $alias
   *   The alias to be used to store the result in.
   * @param string $field
   *   The name of the field for which to get its length.
   */
  public function addFieldLength($alias, $field) {
    if (!empty($alias) && !empty($field)) {
      $this->mongodbFieldsLength[$alias] = $field;
    }
  }

  /**
   * Add a field to the query with a date string value.
   *
   * @param string $alias
   *   The alias to be used to store the result in.
   * @param string $field
   *   The name of the field for the basis of the date string. The field must be
   *   a date field.
   * @param string $format
   *   The format used to create the date string.
   */
  public function addDateDateFormattedField($alias, $field, $format) {
    if (!empty($alias) && !empty($field) && !empty($format)) {
      $this->mongodbDateDateFormattedFields[$alias] = [
        'field' => $field,
        'alias' => $alias,
        'format' => $format,
      ];
    }
  }

  /**
   * Add a field to the query with a date string value.
   *
   * @param string $alias
   *   The alias to be used to store the result in.
   * @param string $field
   *   The name of the field for the basis of the date string. The field must be
   *   a string field with a date value.
   * @param string $format
   *   The format used to create the date string.
   */
  public function addDateStringFormattedField($alias, $field, $format) {
    if (!empty($alias) && !empty($field) && !empty($format)) {
      $this->mongodbDateStringFormattedFields[$alias] = [
        'alias' => $alias,
        'field' => $field,
        'format' => $format,
      ];
    }
  }

  /**
   * Add a field to the query with value of the field.
   *
   * @param string $alias
   *   The alias to be used to store the result in.
   * @param string $field
   *   The name of the field for the value if not empty.
   * @param string $value
   *   The value to use if the field has no value.
   */
  public function addCoalesceValueField($alias, $field, $value) {
    if (!empty($alias) && !empty($field) && !empty($value)) {
      $this->mongodbCoalesceValueFields[$alias] = [
        'alias' => $alias,
        'field' => $field,
        'value' => $value,
      ];
    }
  }

  /**
   * Add a field to the query with the greatest value.
   *
   * @param string $alias
   *   The alias to be used to store the result in.
   * @param array $fields
   *   The list of fields to get the greatest value from.
   * @param array $values
   *   The list of values to get the greatest value from.
   */
  public function addGreatestField($alias, array $fields = [], array $values = []) {
    if (!empty($alias) && (count($fields) + count($values) >= 2)) {
      $this->mongodbGreatestFields[$alias] = [
        'alias' => $alias,
        'fields' => $fields,
        'values' => $values,
      ];
    }
  }

  /**
   * Add a field to the query before any joins are added.
   *
   * @param string $alias
   *   The alias to be used to store the result in.
   * @param string $field
   *   The name of the field for the value.
   */
  public function addPreJoinField($alias, $field) {
    if (!empty($alias) && !empty($field)) {
      $this->mongodbPreJoinFields[$alias] = $field;
    }
  }

  /**
   * Path to be unwound before the condition.
   *
   * @param string $path
   *   The path of the embedded table to be unwound.
   *
   * @return $this
   *   The select query.
   */
  public function addFilterUnwindPath($path): self {
    $this->mongodbFilterUnwindPaths[] = $path;

    return $this;
  }

  /**
   * Add a field to the query with the sum of the multiplications.
   *
   * @param string $alias
   *   The alias to be used to store the result in.
   * @param array $fields
   *   The list of fields to multiply.
   * @param array $values
   *   The list of values to multiply by.
   */
  public function addSumMultiplyExpression($alias, $fields, $values) {
    $this->expressions[$alias] = [
      'expression' => '',
      'type' => 'sum_multiply',
      'fields' => $fields,
      'values' => $values,
      'alias' => $alias,
      'arguments' => [],
    ];

    return $alias;
  }

  /**
   * Add condition to a group by result.
   *
   * @param string $type
   *   The type of group by operation.
   * @param string $field
   *   The name of the field to use for the group by operation.
   * @param mixed $value
   *   The value of the condition to test against.
   * @param string $operator
   *   The operator to use in the condition.
   */
  public function havingConditionWithType($type, $field, $value = NULL, $operator = '=') {
    if (!in_array($type, ['COUNT', 'SUM'], TRUE)) {
      throw new MongodbSQLException("MongoDB does not support $type in the Select::havingConditionWithType().");
    }

    $this->expressions[$field] = [
      'expression' => '',
      'type' => $type,
      'alias' => $field,
    ];

    $this->having->condition($field, $value, $operator);

    return $this;
  }

  /**
   * Unwind a join result and add the fields.
   *
   * @param string $alias
   *   The alias to from the added join.
   * @param array $fields
   *   The list of fields to add to the query.
   */
  public function unwindJoinAndAddFields($alias, array $fields = []) {
    if (!empty($alias)) {
      $this->mongodbUnwindJoinAndAddFields[$alias] = [
        'fields' => $fields,
        'alias' => $alias,
      ];
    }
  }

  /**
   * Create a temporary table to hold the result of the query.
   *
   * @param string $name
   *   The name for the temporary table.
   */
  public function createTemporaryTable($name) {
    if (!empty($name)) {
      $this->mongodbTemporaryTable = $name;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addExpressionConstant(string $constant, ?string $alias = NULL) {
    $alias = $this->getExpressionAlias($alias);

    $this->expressions[$alias] = [
      'type' => 'constant',
      'constant' => $constant,
      'alias' => $alias,
      'arguments' => [],
    ];

    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  public function addExpressionField(string $field, ?string $alias = NULL) {
    $alias = $this->getExpressionAlias($alias);

    $this->expressions[$alias] = [
      'type' => 'field',
      'field' => $field,
      'alias' => $alias,
      'arguments' => [],
    ];

    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  public function addExpressionMax(string $field, ?string $alias = NULL) {
    $alias = $this->getExpressionAlias($alias);

    $this->addGroupByOperation($alias, $field, 'MAX');

    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  public function addExpressionMin(string $field, ?string $alias = NULL) {
    $alias = $this->getExpressionAlias($alias);

    $this->expressions[$alias] = [
      'type' => 'min',
      'field' => $field,
      'alias' => $alias,
      'arguments' => [],
    ];

    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  public function addExpressionSum(string $field, ?string $alias = NULL) {
    $alias = $this->getExpressionAlias($alias);

    $this->expressions[$alias] = [
      'type' => 'sum',
      'field' => $field,
      'alias' => $alias,
      'arguments' => [],
    ];

    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  public function addExpressionCount(string $field, ?string $alias = NULL) {
    $alias = $this->getExpressionAlias($alias);

    $this->expressions[$alias] = [
      'type' => 'count',
      'field' => $field,
      'alias' => $alias,
      'arguments' => [],
    ];

    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  public function addExpressionCountAll(?string $alias = NULL) {
    $alias = $this->getExpressionAlias($alias);

    $this->expressions[$alias] = [
      'type' => 'count_all',
      'alias' => $alias,
      'arguments' => [],
    ];

    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  public function addExpressionCountDistinct(string $field, ?string $alias = NULL) {
    $alias = $this->getExpressionAlias($alias);

    $this->expressions[$alias] = [
      'type' => 'count_distinct',
      'field' => $field,
      'alias' => $alias,
      'arguments' => [],
    ];

    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  public function addExpressionCoalesce(array $fields, ?string $alias = NULL) {
    $alias = $this->getExpressionAlias($alias);

    $this->mongodbCoalesceFields[$alias] = [
      'alias' => $alias,
      'fields' => $fields,
    ];

    return $alias;
  }

  /**
   * Helper method for getting the alias for the expression.
   *
   * @param string|null $alias
   *   The base alias.
   *
   * @return string|null
   *   The alias for the expression.
   */
  protected function getExpressionAlias(?string $alias = NULL) {
    if (empty($alias)) {
      $alias = 'expression';
    }

    $alias_candidate = $alias;
    $count = 2;
    while (!empty($this->expressions[$alias_candidate])) {
      $alias_candidate = $alias . '_' . $count++;
    }
    $alias = $alias_candidate;

    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  public function addExpression($expression, $alias = NULL, $arguments = []) {
    throw new MongodbSQLException('MongoDB does not support methods with SQL string input.');
  }

  /**
   * {@inheritdoc}
   */
  public function union(SelectInterface $query, $type = '') {
    throw new MongodbSQLException('MongoDB does not support UNION queries.');
  }

  /**
   * {@inheritdoc}
   */
  public function addJoin($type, $table, $alias = NULL, $condition = NULL, $arguments = []) {
    if (!empty($condition) && !$condition instanceof ConditionInterface) {
      throw new MongodbSQLException('Joins without the $condition argument being an instance of ConditionInterface is not supported by MongoDB');
    }
    if ($table instanceof SelectInterface) {
      throw new MongodbSQLException('Subqueries are not supported by MongoDB');
    }

    return parent::addJoin($type, $table, $alias, $condition, $arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function havingCondition($field, $value = NULL, $operator = NULL) {
    if (in_array($field, $this->group) && (substr($field, 0, 4) != '_id.')) {
      // If the field is being in the group by, then add the '_id.' to the
      // field.
      $field = '_id.' . $field;
    }

    return parent::havingCondition($field, $value, $operator);
  }

  /**
   * {@inheritdoc}
   */
  public function having($snippet, $args = []) {
    throw new MongodbSQLException('MongoDB does not support methods with SQL string input. Use the method Select::havingCondition() instead of Select::having().');
  }

  /**
   * {@inheritdoc}
   */
  public function &getTables() {
    $tables = $this->tables;

    // Add the "all_fields" variable to the $tables array. The "all_fields"
    // variable is not used by MongoDB.
    foreach ($this->mongodbAllFieldsTables as $table_alias) {
      $tables[$table_alias]['all_fields'] = TRUE;
    }

    return $tables;
  }

  /**
   * {@inheritdoc}
   */
  public function orderRandom() {
    $this->mongodbRandomOrder = TRUE;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function countQuery() {
    // Create our new query object that we will mutate into a count query.
    $count = clone($this);

    // Order by is not useful in a count query.
    $count->order = [];

    // Remove the table from the "all_fields" table list.
    $count->mongodbAllFieldsTables = array_diff($count->mongodbAllFieldsTables, [$count->mongodbBaseAlias]);

    // Let MongoDB perform a count query.
    $count->mongodbCountQuery = TRUE;

    return $count;
  }

  /**
   * {@inheritdoc}
   */
  public function fields($table_alias, array $fields = []) {
    if ($fields) {
      foreach ($fields as $field) {
        // We don't care what alias was assigned.
        $this->addField($table_alias, $field);
      }
    }
    else {
      // We want all fields from this table.
      $added_field = FALSE;
      if (isset($this->tables[$table_alias]['table'])) {
        $table = $this->tables[$table_alias]['table'];
        $table_fields = $this->connection->tableInformation()->getTableFields($table);
        if (!empty($table_fields) && is_array($table_fields)) {
          $table_field_names = array_keys($table_fields);
          foreach ($table_field_names as $table_field_name) {
            $this->addField($table_alias, $table_field_name);
            $added_field = TRUE;
          }
        }
      }

      if (!$added_field) {
        // Throw an exception for no fields added.
      }

      // Fake the "all_fields" setting.
      $this->mongodbAllFieldsTables += [$table_alias];
    }

    return $this;
  }

  /**
   * Groups the result set by the specified field.
   *
   * @param string $alias
   *   The alias for which to group.
   * @param string $field
   *   The field on which to group. This should be the field as aliased.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The called object.
   */
  public function groupByAlias($alias, $field) {
    $this->group[$alias] = $field;
    return $this;
  }

  /**
   * Helper method for reusing the select condition.
   *
   * @return \Drupal\Core\Database\Query\ConditionInterface
   *   The cloned condition object.
   */
  public function cloneCondition() {
    return clone($this->condition);
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // If validation fails, simply return NULL. Note that validation routines
    // in preExecute() may throw exceptions instead.
    if (!$this->preExecute()) {
      return NULL;
    }

    $this->createMongodbQuery();

    // Explicitly remove the base field "_id" if it is not explicitly added.
    if ($this->mongodbRemoveIdField && !empty($this->mongodbProjection)) {
      $this->mongodbProjection += ['_id' => 0];
    }

    if (empty($this->mongodbBaseTable)) {
      throw new MongodbSQLException('The mongodbBaseTable is not set. MongoDB needs this for selecting data from the database.');
    }

    $prefixed_table = $this->connection->getPrefix() . $this->mongodbBaseTable;

    $options = $this->queryOptions;

    // Add the session to the query.
    $options['session'] = $this->connection->getMongodbSession();

    if ($this->mongodbUseAggregate) {
      $pipeline = [];
      $unwound_tables = [];

      if (!empty($this->mongodbEmbeddedTableToUseAsBaseTable)) {
        $embedded_table_parts = explode('.', $this->mongodbEmbeddedTableToUseAsBaseTable);
        $unwind = '';
        foreach ($embedded_table_parts as $embedded_table_part) {
          $unwind = (!empty($unwind) ? $unwind . '.' : '') . $embedded_table_part;

          // Not sure about this solution. This is needed when there is a
          // group-by aggregation.
          if (!empty($this->mongodbGroup)) {
            $pipeline[] = [
              '$unwind' => [
                'path' => '$' . $unwind,
                'preserveNullAndEmptyArrays' => TRUE,
              ],
            ];
          }
          else {
            $pipeline[] = ['$unwind' => '$' . $unwind];
          }
          $unwound_tables[] = $unwind;
        }

        $pipeline[] = [
          '$replaceRoot' => [
            'newRoot' => [
              '$mergeObjects' => [
                '$$ROOT',
                '$' . $this->mongodbEmbeddedTableToUseAsBaseTable,
              ],
            ],
          ],
        ];
      }

      if (!empty($this->mongodbAddFieldsPreJoin)) {
        $pipeline[] = ['$addFields' => $this->mongodbAddFieldsPreJoin];
      }

      foreach ($this->mongodbLookupUnwindPaths as $lookup_unwind_path) {
        if (!empty($lookup_unwind_path) && (!in_array($lookup_unwind_path, $unwound_tables, TRUE))) {
          $embedded_table_parts = explode('.', $lookup_unwind_path);
          $unwind = '';
          foreach ($embedded_table_parts as $embedded_table_part) {
            $unwind = (!empty($unwind) ? $unwind . '.' : '') . $embedded_table_part;

            $pipeline[] = ['$unwind' => '$' . $unwind];
            $unwound_tables[] = $unwind;
          }
        }
      }

      foreach ($this->mongodbLookups as $mongodbLookup) {
        $pipeline[] = $mongodbLookup;
        if (is_array($mongodbLookup)) {
          foreach ($mongodbLookup as $mongodbLookupKey => $mongodbLookupElement) {
            if (($mongodbLookupKey == '$unwind') && (isset($mongodbLookupElement['path']))) {
              $unwound_tables[] = substr($mongodbLookupElement['path'], 1);
            }
          }
        }
      }

      foreach ($this->mongodbUnwindJoinAndAddFields as $mongodbUnwindJoinAlias => $mongodbUnwindJoinAndAddField) {
        if (!in_array($mongodbUnwindJoinAlias, $unwound_tables, TRUE)) {
          $pipeline[] = [
            '$unwind' => [
              'path' => '$' . $mongodbUnwindJoinAlias,
              'preserveNullAndEmptyArrays' => TRUE,
            ],
          ];
          $unwound_tables[] = $mongodbUnwindJoinAlias;
        }
        $unwind_join_add_fields = [];
        foreach ($mongodbUnwindJoinAndAddField['fields'] as $mongodbUnwindJoinAddField) {
          $unwind_join_add_fields[$mongodbUnwindJoinAddField] = '$' . $mongodbUnwindJoinAlias . '.' . $mongodbUnwindJoinAddField;
        }
        if (!empty($unwind_join_add_fields)) {
          $pipeline[] = ['$addFields' => $unwind_join_add_fields];
        }
      }

      // Place the $match early in the aggregation pipeline. Accept when it is a
      // query with a group_by and a having clause.
      foreach ($this->mongodbFilterUnwindPaths as $filter_unwind_path) {
        if (!empty($filter_unwind_path) && (!in_array($filter_unwind_path, $unwound_tables, TRUE))) {
          $embedded_table_parts = explode('.', $filter_unwind_path);
          $unwind = '';
          foreach ($embedded_table_parts as $embedded_table_part) {
            $unwind = (!empty($unwind) ? $unwind . '.' : '') . $embedded_table_part;

            $pipeline[] = [
              '$unwind' => [
                'path' => '$' . $unwind,
                'preserveNullAndEmptyArrays' => TRUE,
              ],
            ];
            $unwound_tables[] = $unwind;
          }
        }
      }

      if (!empty($this->mongodbAddFields)) {
        foreach ($this->mongodbAddFields as $alias => $mongodbAddField) {
          $pipeline[] = ['$addFields' => [$alias => $mongodbAddField]];
        }
      }

      if (!empty($this->mongodbAggregateFilter)) {
        $recompile = FALSE;
        foreach ($this->condition->getElemMatchEmbeddedTables() as $embedded_table) {
          if (in_array($embedded_table, $unwound_tables, TRUE)) {
            $recompile = TRUE;
          }
        }
        if ($recompile) {
          $this->condition->setUnwoundTables($unwound_tables);
          $this->condition->compile($this->connection, $this);
          $this->mongodbAggregateFilter = $this->condition->toMongoAggregateArray();
        }
        $pipeline[] = ['$match' => $this->mongodbAggregateFilter];
      }

      if (!empty($this->mongodbProjection)) {
        // Add the add fields to the projection when the projection is not
        // empty.
        if (!empty($this->mongodbAddFieldsPreJoin)) {
          foreach ($this->mongodbAddFieldsPreJoin as $alias => $add_field) {
            $this->mongodbProjection[$alias] = 1;
          }
        }
        if (!empty($this->mongodbAddFields)) {
          foreach ($this->mongodbAddFields as $alias => $add_field) {
            $this->mongodbProjection[$alias] = 1;
          }
        }
        $pipeline[] = ['$project' => $this->mongodbProjection];
      }

      if (!empty($this->mongodbUnwind)) {
        sort($this->mongodbUnwind);
        foreach ($this->mongodbUnwind as $unwind) {
          if (!empty($unwind) && (!in_array($unwind, $unwound_tables, TRUE))) {
            $pipeline[] = [
              '$unwind' => [
                'path' => $unwind,
                'preserveNullAndEmptyArrays' => TRUE,
              ],
            ];
            $unwound_tables[] = $unwind;
          }
        }
      }

      if (!empty($this->mongodbGroup)) {
        $pipeline[] = ['$group' => $this->mongodbGroup];
      }

      if (!empty($this->mongodbHavingFilter)) {
        $pipeline[] = ['$match' => $this->mongodbHavingFilter];
      }

      if (!empty($this->mongodbSort) && !$this->mongodbCountQuery) {
        // Sorting fails when sorting by multiple values from the same embedded
        // table.
        if ($this->mongodbSortSeparate) {
          foreach ($this->mongodbSort as $sort_key => $sort_value) {
            $pipeline[] = ['$sort' => [$sort_key => $sort_value]];
          }
        }
        else {
          $pipeline[] = ['$sort' => $this->mongodbSort];
        }
      }
      if (!empty($this->mongodbAddFieldsLast)) {
        $pipeline[] = ['$addFields' => $this->mongodbAddFieldsLast];
      }
      if (isset($this->mongodbSkip)) {
        $pipeline[] = ['$skip' => $this->mongodbSkip];
      }
      if (isset($this->mongodbLimit)) {
        $pipeline[] = ['$limit' => $this->mongodbLimit];
      }

      if ($this->mongodbCountQuery) {
        // Change the query to a count query.
        $pipeline[] = ['$count' => 'total_rows'];

        // Return the query as its string value.
        if ($this->mongodbQueryStringValue) {
          // Reset the class property. So that the next time the query will be
          // executed.
          $this->mongodbQueryStringValue = FALSE;

          return 'SELECT COUNT WITH AGGREGATE PIPELINE: ' . serialize($pipeline);
        }

        if ($this->connection->isEventEnabled(StatementExecutionStartEvent::class)) {
          $startEvent = new StatementExecutionStartEvent(
            spl_object_id($this),
            $this->connection->getKey(),
            $this->connection->getTarget(),
            'SELECT COUNT WITH AGGREGATE PIPELINE: ' . serialize($pipeline),
            [],
            $this->connection->findCallerFromDebugBacktrace()
          );
          $this->connection->dispatchEvent($startEvent);
        }

        $results = $this->connection->getConnection()->selectCollection($prefixed_table)->aggregate(
          $pipeline,
          [
            'session' => $this->connection->getMongodbSession(),
          ],
        )->toArray();
        $result = reset($results);
        $count = is_object($result) && isset($result->total_rows) ? $result->total_rows : 0;

        if (isset($startEvent) && $this->connection->isEventEnabled(StatementExecutionEndEvent::class)) {
          $this->connection->dispatchEvent(new StatementExecutionEndEvent(
            $startEvent->statementObjectId,
            $startEvent->key,
            $startEvent->target,
            $startEvent->queryString,
            $startEvent->args,
            $startEvent->caller,
            $startEvent->time
          ));
        }

        return new StatementCountQuery($this->connection, $count, $options);
      }
      else {
        // The creation of a temporary table must always be the last step in the
        // aggregation pipeline.
        if (isset($this->mongodbTemporaryTable)) {
          $pipeline[] = ['$out' => $this->connection->getPrefix() . $this->mongodbTemporaryTable];
        }

        // Return the query as its string value.
        if ($this->mongodbQueryStringValue) {
          // Reset the class property. So that the next time the query will be
          // executed.
          $this->mongodbQueryStringValue = FALSE;

          return 'SELECT WITH AGGREGATE PIPELINE: ' . serialize($pipeline);
        }

        if ($this->connection->isEventEnabled(StatementExecutionStartEvent::class)) {
          $startEvent = new StatementExecutionStartEvent(
            spl_object_id($this),
            $this->connection->getKey(),
            $this->connection->getTarget(),
            'SELECT WITH AGGREGATE PIPELINE: ' . serialize($pipeline),
            [],
            $this->connection->findCallerFromDebugBacktrace()
          );
          $this->connection->dispatchEvent($startEvent);
        }

        $cursor = $this->connection->getConnection()->selectCollection($prefixed_table)->aggregate(
          $pipeline,
          [
            'useCursor' => TRUE,
            'session' => $this->connection->getMongodbSession(),
          ],
        );

        if (isset($startEvent) && $this->connection->isEventEnabled(StatementExecutionEndEvent::class)) {
          $this->connection->dispatchEvent(new StatementExecutionEndEvent(
            $startEvent->statementObjectId,
            $startEvent->key,
            $startEvent->target,
            $startEvent->queryString,
            $startEvent->args,
            $startEvent->caller,
            $startEvent->time
          ));
        }

        // The creation of a temporary table always returns the temporary table
        // name.
        if (isset($this->mongodbTemporaryTable)) {
          return $this->mongodbTemporaryTable;
        }
      }
    }
    elseif ($this->mongodbCountQuery) {
      $options = [];
      if (isset($this->mongodbLimit)) {
        $options['limit'] = $this->mongodbLimit;
      }
      if (isset($this->mongodbSkip)) {
        $options['skip'] = $this->mongodbSkip;
      }

      // SQL99 does not support count queries on distinct multiple fields. In
      // the method $this->createMongodbQuery() will the distinct status be
      // removed in such cases.
      if ($this->distinct && (count($this->fields) == 1)) {
        $field = reset($this->fields);

        // Return the query as its string value.
        if ($this->mongodbQueryStringValue) {
          // Reset the class property. So that the next time the query will be
          // executed.
          $this->mongodbQueryStringValue = FALSE;

          return 'SELECT COUNT WITH DISTINCT FIELD: ' . serialize($field['field'] ?? '') . ' FILTER: ' . serialize($this->mongodbFilter);
        }

        if ($this->connection->isEventEnabled(StatementExecutionStartEvent::class)) {
          $startEvent = new StatementExecutionStartEvent(
            spl_object_id($this),
            $this->connection->getKey(),
            $this->connection->getTarget(),
            'SELECT COUNT WITH DISTINCT FIELD: ' . serialize($field['field'] ?? '') . ' FILTER: ' . serialize($this->mongodbFilter),
            [],
            $this->connection->findCallerFromDebugBacktrace()
          );
          $this->connection->dispatchEvent($startEvent);
        }

        $count = count($this->connection->getConnection()->selectCollection($prefixed_table)->distinct($field['field'], $this->mongodbFilter), $options);

        if (isset($startEvent) && $this->connection->isEventEnabled(StatementExecutionEndEvent::class)) {
          $this->connection->dispatchEvent(new StatementExecutionEndEvent(
            $startEvent->statementObjectId,
            $startEvent->key,
            $startEvent->target,
            $startEvent->queryString,
            $startEvent->args,
            $startEvent->caller,
            $startEvent->time
          ));
        }
      }
      else {
        // Return the query as its string value.
        if ($this->mongodbQueryStringValue) {
          // Reset the class property. So that the next time the query will be
          // executed.
          $this->mongodbQueryStringValue = FALSE;

          // Remove the session from the options as it is not serializable.
          unset($options['session']);

          return 'SELECT COUNT WITH COUNT FILTER: ' . serialize($this->mongodbFilter) . ' OPTIONS: ' . serialize($options);
        }

        if ($this->connection->isEventEnabled(StatementExecutionStartEvent::class)) {
          $startEvent = new StatementExecutionStartEvent(
            spl_object_id($this),
            $this->connection->getKey(),
            $this->connection->getTarget(),
            'SELECT COUNT WITH COUNT FILTER: ' . serialize($this->mongodbFilter) . ' OPTIONS: ' . serialize($options),
            [],
            $this->connection->findCallerFromDebugBacktrace()
          );
          $this->connection->dispatchEvent($startEvent);
        }

        $count = $this->connection->getConnection()->selectCollection($prefixed_table)->count($this->mongodbFilter, $options);

        if (isset($startEvent) && $this->connection->isEventEnabled(StatementExecutionEndEvent::class)) {
          $this->connection->dispatchEvent(new StatementExecutionEndEvent(
            $startEvent->statementObjectId,
            $startEvent->key,
            $startEvent->target,
            $startEvent->queryString,
            $startEvent->args,
            $startEvent->caller,
            $startEvent->time
          ));
        }
      }

      return new StatementCountQuery($this->connection, $count, $options);
    }
    else {
      $options['projection'] = $this->mongodbProjection;
      if (!empty($this->mongodbSort)) {
        $options['sort'] = $this->mongodbSort;
      }
      if (isset($this->mongodbSkip)) {
        $options['skip'] = $this->mongodbSkip;
      }
      if (isset($this->mongodbLimit)) {
        $options['limit'] = $this->mongodbLimit;
      }
      if (!empty($this->comments) && is_array($this->comments)) {
        $options['comment'] = $this->connection->makeComment($this->comments);
      }

      // Return the query as its string value.
      if ($this->mongodbQueryStringValue) {
        // Reset the class property. So that the next time the query will be
        // executed.
        $this->mongodbQueryStringValue = FALSE;

        // Remove the session from the options as it is not serializable.
        unset($options['session']);

        return 'SELECT WITH FIND FILTER: ' . serialize($this->mongodbFilter) . ' OPTIONS: ' . serialize($options);
      }

      if ($this->connection->isEventEnabled(StatementExecutionStartEvent::class)) {
        // Remove the session from the options as it is not serializable.
        $options_to_serialize = $options;
        unset($options_to_serialize['session']);

        $startEvent = new StatementExecutionStartEvent(
          spl_object_id($this),
          $this->connection->getKey(),
          $this->connection->getTarget(),
          'SELECT WITH FIND FILTER: ' . serialize($this->mongodbFilter) . ' OPTIONS: ' . serialize($options_to_serialize),
          [],
          $this->connection->findCallerFromDebugBacktrace()
        );
        $this->connection->dispatchEvent($startEvent);
      }

      $cursor = $this->connection->getConnection()->selectCollection($prefixed_table)->find($this->mongodbFilter, $options);

      if (isset($startEvent) && $this->connection->isEventEnabled(StatementExecutionEndEvent::class)) {
        $this->connection->dispatchEvent(new StatementExecutionEndEvent(
          $startEvent->statementObjectId,
          $startEvent->key,
          $startEvent->target,
          $startEvent->queryString,
          $startEvent->args,
          $startEvent->caller,
          $startEvent->time
        ));
      }
    }

    $fields = [];
    foreach ($this->mongodbProjection as $key => $value) {
      if ($value) {
        $fields[] = $key;
      }
    }

    if ($this->mongodbRandomOrder) {
      $options['random_order'] = 1;
    }

    $statement = new Statement($this->connection, $cursor, $fields);
    $statement->execute(NULL, $options);
    return $statement;
  }

  /**
   * Helper method for getting the used table names and aliases.
   *
   * @param string $table
   *   The table name to skip.
   * @param string $alias
   *   The table alias to skip.
   *
   * @return array
   *   The list with used table names and aliases.
   */
  protected function getTableNamesAndAliases(string $table, string $alias): array {
    $list = [];
    foreach ($this->tables as $join) {
      if (!in_array($join['table'], [$table, $alias, $this->mongodbBaseTable, $this->mongodbBaseAlias], TRUE)) {
        $list[] = $join['table'];
      }
      if (!in_array($join['alias'], [$table, $alias, $this->mongodbBaseTable, $this->mongodbBaseAlias], TRUE)) {
        $list[] = $join['alias'];
      }
    }

    return array_unique($list);
  }

  /**
   * Helper method for updating the join condition.
   *
   * We cannot use the function \array_walk_recursive(), because that function
   * walks all the values of an array. It does not walk all keys of the array.
   *
   * @param array $condition
   *   The compiled join condition.
   * @param string $right_table
   *   The right table of the join.
   * @param string $right_alias
   *   The right alias of the join.
   * @param array $lookup_let
   *   Array of query variables that must be added to the join.
   * @param array $lookup_left_unwind_paths
   *   The embedded table left paths to be unwound.
   * @param array $lookup_right_unwind_paths
   *   The embedded table right paths to be unwound.
   */
  protected function updateCompiledJoinCondition(array &$condition, string $right_table, string $right_alias, array &$lookup_let, array &$lookup_left_unwind_paths, array &$lookup_right_unwind_paths): void {
    foreach ($condition as $key => &$value) {
      if (is_string($key)) {
        $new_key = $key;
        if (str_starts_with($key, $this->mongodbBaseTable . '.')) {
          // Remove base table from the condition value.
          $new_key = str_replace($this->mongodbBaseTable . '.', '', $key);
        }
        elseif (str_starts_with($key, $this->mongodbBaseAlias . '.')) {
          // Remove base table from the condition value.
          $new_key = str_replace($this->mongodbBaseAlias . '.', '', $key);
        }
        foreach ($this->getTableNamesAndAliases($right_table, $right_alias) as $used_name_or_alias) {
          if (str_starts_with($key, $used_name_or_alias . '.')) {
            $new_key = str_replace($used_name_or_alias . '.', '', $key);
          }
        }

        // Remove the right table name from the condition key.
        if (str_starts_with($key, $right_table . '.')) {
          $new_key = str_replace($right_table . '.', '', $key);
        }
        // Remove the alias alias name from the condition key.
        if (str_starts_with($key, $right_alias . '.')) {
          $new_key = str_replace($right_alias . '.', '', $key);
        }

        // Array keys cannot be updated by reference.
        if ($key !== $new_key) {
          $condition[$new_key] = $condition[$key];
          unset($condition[$key]);
        }
      }
      if (is_string($value)) {
        $add_to_lookup_let = FALSE;
        if (str_starts_with($value, '$' . $this->mongodbBaseTable . '.')) {
          // Remove base table from the condition value.
          $value = str_replace('$' . $this->mongodbBaseTable . '.', '$', $value);
          $add_to_lookup_let = TRUE;
          $last_dot_position = strrpos($value, '.');
          if ($last_dot_position !== FALSE) {
            $unwind_path = '';
            $unwind_path_parts = explode('.', substr($value, 0, $last_dot_position));
            foreach ($unwind_path_parts as $unwind_path_part) {
              $unwind_path = (!empty($unwind_path) ? $unwind_path . '.' : '') . $unwind_path_part;
              $lookup_left_unwind_paths[] = $unwind_path;
            }
          }
        }
        elseif (str_starts_with($value, '$' . $this->mongodbBaseAlias . '.')) {
          // Remove base alias from the condition value.
          $value = str_replace('$' . $this->mongodbBaseAlias . '.', '$', $value);
          $add_to_lookup_let = TRUE;
          $last_dot_position = strrpos($value, '.');
          if ($last_dot_position !== FALSE) {
            $unwind_path = '';
            $unwind_path_parts = explode('.', substr($value, 0, $last_dot_position));
            foreach ($unwind_path_parts as $unwind_path_part) {
              $unwind_path = (!empty($unwind_path) ? $unwind_path . '.' : '') . $unwind_path_part;
              $lookup_left_unwind_paths[] = $unwind_path;
            }
          }
        }
        foreach ($this->getTableNamesAndAliases($right_table, $right_alias) as $used_name_or_alias) {
          if (str_starts_with($value, '$' . $used_name_or_alias . '.')) {
            $add_to_lookup_let = TRUE;
            $last_dot_position = strrpos($value, '.');
            if ($last_dot_position !== FALSE) {
              $unwind_path = '';
              $unwind_path_parts = explode('.', substr($value, 0, $last_dot_position));
              foreach ($unwind_path_parts as $unwind_path_part) {
                $unwind_path = (!empty($unwind_path) ? $unwind_path . '.' : '') . $unwind_path_part;
                $lookup_left_unwind_paths[] = $unwind_path;
              }
            }
          }
        }

        if ($add_to_lookup_let) {
          // Replace the used left table names and aliases with lookup "let".
          $lookup_let_key = str_replace('.', '_', $value);
          if (str_starts_with($lookup_let_key, '$')) {
            $lookup_let_key = substr($lookup_let_key, 1);
          }

          // Add the variable to the lookup let.
          $lookup_let[$lookup_let_key] = $value;

          // Update the variable to use the key from the lookup let.
          $value = '$$' . $lookup_let_key;
        }

        // Remove the right table name from the condition.
        if (str_starts_with($value, '$' . $right_table . '.')) {
          $value = str_replace('$' . $right_table . '.', '$', $value);
          $last_dot_position = strrpos($value, '.');
          if ($last_dot_position !== FALSE) {
            $unwind_path = '';
            $unwind_path_parts = explode('.', substr($value, 0, $last_dot_position));
            foreach ($unwind_path_parts as $unwind_path_part) {
              $unwind_path = (!empty($unwind_path) ? $unwind_path . '.' : '') . $unwind_path_part;
              $lookup_right_unwind_paths[] = $unwind_path;
            }
          }
        }
        // Remove the right alias name from the condition.
        if (str_starts_with($value, '$' . $right_alias . '.')) {
          $value = str_replace('$' . $right_alias . '.', '$', $value);
          $last_dot_position = strrpos($value, '.');
          if ($last_dot_position !== FALSE) {
            $unwind_path = '';
            $unwind_path_parts = explode('.', substr($value, 0, $last_dot_position));
            foreach ($unwind_path_parts as $unwind_path_part) {
              $unwind_path = (!empty($unwind_path) ? $unwind_path . '.' : '') . $unwind_path_part;
              $lookup_right_unwind_paths[] = $unwind_path;
            }
          }
        }
      }
      elseif (is_array($value)) {
        // Recursive walk all array's.
        $this->updateCompiledJoinCondition($value, $right_table, $right_alias, $lookup_let, $lookup_left_unwind_paths, $lookup_right_unwind_paths);
      }
    }
  }

  /**
   * Create the MongoDB select query.
   */
  protected function createMongodbQuery(): void {
    // For convenience, we compile the query ourselves if the caller forgot
    // to do it. This allows constructs like "(string) $query" to work. When
    // the query will be executed, it will be recompiled using the proper
    // placeholder generator anyway.
    if (!$this->compiled()) {
      $this->compile($this->connection, $this);
    }

    if (!empty($this->mongodbEmbeddedTableToUseAsBaseTable)) {
      $this->mongodbUseAggregate = TRUE;
    }

    // The tables.
    $this->mongodbLookups = [];

    // Add the joins to the select query.
    foreach ($this->tables as $mongodbJoin) {
      // Only add real table joins to the select query.
      if (!empty($mongodbJoin['join type'])) {
        $lookup_pipeline = [];
        $lookup_let = [];

        if (isset($mongodbJoin['condition']) && ($mongodbJoin['condition'] instanceof Condition)) {
          $lookup_left_unwind_paths = [];
          $lookup_right_unwind_paths = [];

          // Let the condition know that we are doing a join condition.
          $mongodbJoin['condition']->setMongodbJoinCondition();

          // Compile the condition and update the alias variable in the
          // condition.
          $mongodbJoin['condition']->compile($this->connection, $this);
          $condition_compiled = $mongodbJoin['condition']->toMongoAggregateArray();
          $this->updateCompiledJoinCondition($condition_compiled, $mongodbJoin['table'], $mongodbJoin['alias'], $lookup_let, $lookup_left_unwind_paths, $lookup_right_unwind_paths);

          $lookup_left_unwind_paths = array_unique($lookup_left_unwind_paths);
          sort($lookup_left_unwind_paths);
          foreach ($lookup_left_unwind_paths as $lookup_left_unwind_path) {
            if ($lookup_left_unwind_path && !in_array($lookup_left_unwind_path, $this->mongodbUnwoundPaths, TRUE)) {
              $this->mongodbLookups[] = [
                '$unwind' => [
                  'path' => $lookup_left_unwind_path,
                  'preserveNullAndEmptyArrays' => TRUE,
                ],
              ];
              $this->mongodbUnwoundPaths[] = $lookup_left_unwind_path;
            }
          }

          $lookup_right_unwind_paths = array_unique($lookup_right_unwind_paths);
          sort($lookup_right_unwind_paths);
          foreach ($lookup_right_unwind_paths as $lookup_right_unwind_path) {
            if ($lookup_right_unwind_path) {
              $lookup_pipeline[] = [
                '$unwind' => [
                  'path' => $lookup_right_unwind_path,
                  'preserveNullAndEmptyArrays' => TRUE,
                ],
              ];
            }
          }

          $lookup_pipeline[] = [
            '$match' => $condition_compiled,
          ];
        }

        $lookup = [
          '$lookup' => [
            'from' => $this->connection->getPrefix() . $mongodbJoin['table'],
            'let' => $lookup_let,
            'pipeline' => $lookup_pipeline,
            'as' => $mongodbJoin['alias'],
          ],
        ];
        if (empty($lookup_let)) {
          unset($lookup['$lookup']['let']);
        }
        $this->mongodbLookups[] = $lookup;

        // Inner join must have a value in the right table. This is a bit of a
        // hack, because an inner join can result in returning multiple row for
        // a single left table row. MongoDB is not able to do that.
        if (strtoupper($mongodbJoin['join type']) == 'INNER') {
          $this->mongodbLookups[] = [
            '$match' => [
              $mongodbJoin['alias'] => [
                '$ne' => [],
              ],
            ],
          ];
        }

        if (!in_array('$' . $mongodbJoin['alias'], $this->mongodbUnwoundPaths, TRUE)) {
          $this->mongodbLookups[] = [
            '$unwind' => [
              'path' => '$' . $mongodbJoin['alias'],
              'preserveNullAndEmptyArrays' => TRUE,
            ],
          ];
          $this->mongodbUnwoundPaths[] = '$' . $mongodbJoin['alias'];
        }

        $this->mongodbUseAggregate = TRUE;
      }
    }

    // Add the MongoDB pre join fields.
    $this->mongodbAddFieldsPreJoin = [];
    foreach ($this->mongodbPreJoinFields as $alias => $field) {
      $this->mongodbAddFieldsPreJoin[$this->connection->escapeField($alias)] = '$' . $field;
      $this->mongodbProjection[$this->connection->escapeField($alias)] = 1;
      $this->mongodbUseAggregate = TRUE;
    }

    $this->mongodbAddFields = [];

    // Add the MongoDB literal fields.
    foreach ($this->mongodbLiteralFields as $alias => $value) {
      $this->mongodbAddFields[$this->connection->escapeField($alias)] = ['$literal' => $value];
      $this->mongodbUseAggregate = TRUE;
    }

    // Add the MongoDB condition fields.
    foreach ($this->mongodbConditionFields as $alias => $field) {
      $last_dot_position = strrpos($field, '.');
      if ($last_dot_position !== FALSE) {
        $this->mongodbFilterUnwindPaths[] = substr($field, 0, $last_dot_position);
      }
      $this->mongodbAddFields[$this->connection->escapeField($alias)] = '$' . $field;
      $this->mongodbUseAggregate = TRUE;
    }

    // Add the MongoDB multiply field.
    foreach ($this->mongodbMultiplyFields as $alias => $data) {
      $field = ['$ifNull' => ['$' . $data['field'], 0]];
      $this->mongodbAddFields[$this->connection->escapeField($alias)] = ['$multiply' => [$field, $data['value']]];
      $this->mongodbUseAggregate = TRUE;
    }

    // Add the MongoDB sum fields.
    foreach ($this->mongodbSumFields as $alias => $data) {
      $sum_fields = [];
      foreach ($data['fields'] as $field) {
        $sum_fields[] = ['$ifNull' => ['$' . $field, 0]];
      }
      if (!empty($data['value'])) {
        $sum_fields[] = $data['value'];
      }
      $this->mongodbAddFields[$this->connection->escapeField($alias)] = ['$add' => $sum_fields];
      $this->mongodbUseAggregate = TRUE;
    }

    // Add the MongoDB sum multiply fields.
    foreach ($this->mongodbSumMultiplyFields as $alias => $data) {
      $sum_fields = [];
      foreach ($data['fields'] as $field) {
        $sum_fields[] = ['$ifNull' => ['$' . $field, 0]];
      }
      $this->mongodbAddFields[$this->connection->escapeField($alias)] = ['$add' => $sum_fields];
      $this->mongodbUseAggregate = TRUE;
    }

    // Add the MongoDB concat fields.
    foreach ($this->mongodbConcatFields as $alias => $data) {
      $add_fields = [];
      foreach ($data['fields'] as $field) {
        if (in_array($field, $data['integer fields'], TRUE)) {
          $field = ['$substrBytes' => [$field, 0 , -1]];
        }
        if ($field === ' ') {
          $add_fields[] = $field;
        }
        else {
          $add_fields[] = ['$ifNull' => [$field, '']];
        }
      }
      $this->mongodbAddFields[$this->connection->escapeField($alias)] = ['$concat' => $add_fields];
      $this->mongodbUseAggregate = TRUE;
    }

    // Add the MongoDB substring fields.
    foreach ($this->mongodbSubstringFields as $alias => $data) {
      if (!isset($data['start']) || !is_int($data['start']) || (intval($data['start']) < 0)) {
        // Throw error.
      }
      $start = (int) $data['start'];

      if (!isset($data['length']) || !is_int($data['length']) || (intval($data['length']) == 0)) {
        // Throw error.
      }
      $length = (int) $data['length'];

      $last_dot_position = strrpos($data['field'], '.');
      if ($last_dot_position !== FALSE) {
        $this->mongodbFilterUnwindPaths[] = substr($data['field'], 0, $last_dot_position);
      }
      $field = '$' . $data['field'];

      if ($length < 0) {
        $length = [
          '$add' => [
            [
              '$strLenBytes' => [
                '$ifNull' => [
                  $field,
                  '',
                ],
              ],
            ],
            $length,
          ],
        ];
      }

      $this->mongodbAddFields[$this->connection->escapeField($alias)] = [
        '$substrBytes' => [['$ifNull' => [$field, '']], $start, $length],
      ];
      $this->mongodbUseAggregate = TRUE;
    }

    // Add the MongoDB fields length.
    foreach ($this->mongodbFieldsLength as $alias => $field) {
      $this->mongodbAddFields[$this->connection->escapeField($alias)] = ['$strLenCP' => ['$ifNull' => [$field, '']]];
      $this->mongodbUseAggregate = TRUE;
    }

    // Add the MongoDB date formatted fields (from a date field).
    foreach ($this->mongodbDateDateFormattedFields as $alias => $data) {
      $this->mongodbAddFields[$this->connection->escapeField($alias)] = [
        '$dateToString' => [
          'format' => $data['format'],
          'date' => '$' . $this->connection->escapeField($data['field']),
        ],
      ];
      $this->mongodbUseAggregate = TRUE;
    }

    // Add the MongoDB date formatted fields (from a string field).
    foreach ($this->mongodbDateStringFormattedFields as $alias => $data) {
      $this->mongodbAddFields[$this->connection->escapeField($alias)] = [
        '$dateToString' => [
          'format' => $data['format'],
          'date' => [
            '$dateFromString' => [
              'dateString' => '$' . $this->connection->escapeField($data['field']),
            ],
          ],
        ],
      ];
      $this->mongodbUseAggregate = TRUE;
    }

    // Add the MongoDB coalesce value fields.
    foreach ($this->mongodbCoalesceValueFields as $alias => $data) {
      $this->mongodbAddFields[$this->connection->escapeField($alias)] = [
        '$ifNull' => ['$' . $data['field'], $data['value']],
      ];
      $this->mongodbUseAggregate = TRUE;
    }

    // Add the MongoDB coalesce fields.
    foreach ($this->mongodbCoalesceFields as $alias => $data) {
      $coalesce_fields = [];
      foreach ($data['fields'] as $coalesce_field) {
        $coalesce_fields[] = '$' . $this->connection->escapeField($coalesce_field);
      }
      $this->mongodbAddFields[$this->connection->escapeField($alias)] = ['$ifNull' => $coalesce_fields];
      $this->mongodbUseAggregate = TRUE;
    }

    // Add the MongoDB greatest fields.
    foreach ($this->mongodbGreatestFields as $alias => $data) {
      $max_fields = [];
      foreach ($data['fields'] as $field) {
        $max_fields[] = '$' . $field;
      }
      $max_fields = array_merge($max_fields, $data['values']);
      $this->mongodbAddFields[$this->connection->escapeField($alias)] = ['$max' => $max_fields];
      $this->mongodbUseAggregate = TRUE;
    }

    // Create temporary table.
    if (!empty($this->mongodbTemporaryTable)) {
      $this->mongodbUseAggregate = TRUE;
    }

    // SELECT.
    $this->setMongodbProjection();

    // WHERE.
    $this->mongodbFilter = [];
    $this->mongodbAggregateFilter = [];
    if (count($this->condition)) {
      $this->mongodbFilter = $this->condition->toMongoArray();
      $this->mongodbAggregateFilter = $this->condition->toMongoAggregateArray();
    }

    if (!empty($this->mongodbFilterUnwindPaths)) {
      $unnecessary_paths = [];
      if (!empty($this->mongodbBaseAlias)) {
        $unnecessary_paths[] = $this->mongodbBaseAlias;
        $unnecessary_paths[] = '$' . $this->mongodbBaseAlias;
      }
      if (!empty($this->mongodbBaseTable)) {
        $unnecessary_paths[] = $this->mongodbBaseTable;
        $unnecessary_paths[] = '$' . $this->mongodbBaseTable;
      }
      $this->mongodbFilterUnwindPaths = array_diff($this->mongodbFilterUnwindPaths, $unnecessary_paths);
      $this->mongodbFilterUnwindPaths = array_unique($this->mongodbFilterUnwindPaths);
      sort($this->mongodbFilterUnwindPaths);
      $this->mongodbFilterUnwindPaths = array_filter($this->mongodbFilterUnwindPaths);

      // Remove embedded table paths that are also parts of a deeper embedded
      // table path.
      foreach ($this->mongodbFilterUnwindPaths as $key => $mongodbFilterUnwindPath) {
        $matches = array_filter($this->mongodbFilterUnwindPaths, function ($haystack) use ($mongodbFilterUnwindPath) {
          if (strpos($haystack, $mongodbFilterUnwindPath) !== FALSE) {
            return TRUE;
          }
          return FALSE;
        });
        if (count($matches) >= 2) {
          unset($this->mongodbFilterUnwindPaths[$key]);
        }
      }

      $this->mongodbUseAggregate = TRUE;
    }

    if ($this->distinct && $this->mongodbCountQuery && (count($this->fields) > 1)) {
      $this->distinct = FALSE;
    }

    // DISTINCT.
    if ($this->distinct) {
      // Remake a distinct query into an aggregate query.
      foreach ($this->fields as $field) {
        $field_name = $field['field'];
        $this->group[$field_name] = $field_name;
      }
    }

    // GROUP BY.
    $this->setMongodbGroup();
    if (!empty($this->mongodbGroup)) {
      $this->mongodbUseAggregate = TRUE;
    }

    // HAVING.
    if (count($this->having)) {
      $this->mongodbHavingFilter = $this->having->toMongoAggregateArray();
    }

    // UNION.
    if ($this->union) {
      throw new MongodbSQLException('MongoDB does not support UNION queries.');
    }

    // ORDER BY.
    $this->mongodbSort = [];
    if ($this->order) {
      $sort_embedded_tables = [];
      foreach ($this->order as $field => $direction) {
        // Remove $this->mongodbBaseTable or $this->mongodbBaseAlias from the
        // field.
        if (strpos($field, $this->mongodbBaseTable . '.') === 0) {
          $field = substr($field, (strlen($this->mongodbBaseTable) + 1));
        }
        if (strpos($field, $this->mongodbBaseAlias . '.') === 0) {
          $field = substr($field, (strlen($this->mongodbBaseAlias) + 1));
        }

        // If a field name is used in the group by clause, the following part
        // must be prepended to the field name: "_id.".
        $sort_by_field = (isset($this->mongodbGroup['_id']) && in_array($field, array_keys($this->mongodbGroup['_id']), TRUE) ? '_id.' : '') . $this->connection->escapeField($field);
        $this->mongodbSort[$sort_by_field] = (strtoupper($direction) == 'DESC' ? -1 : 1);

        if (isset($this->mongodbProjection[$sort_by_field]) && !is_array($this->mongodbProjection[$sort_by_field])) {
          $last_dot = strrpos($this->mongodbProjection[$sort_by_field], '.');
          if ($last_dot !== FALSE) {
            $embedded_table = substr($this->mongodbProjection[$sort_by_field], 0, $last_dot);
            if ($embedded_table[0] == '$') {
              $embedded_table = substr($embedded_table, 1);
            }
            $sort_embedded_tables[] = $embedded_table;
          }
        }
      }

      $unique_embedded_tables = [];
      foreach ($sort_embedded_tables as $sort_embedded_table) {
        $embedded_table = '';
        $sort_embedded_table_parts = explode('.', $sort_embedded_table);
        foreach ($sort_embedded_table_parts as $sort_embedded_table_part) {
          if (empty($embedded_table)) {
            $embedded_table = $sort_embedded_table_part;
          }
          else {
            $embedded_table .= '.' . $sort_embedded_table_part;
          }
          $unique_embedded_tables[] = $embedded_table;
        }
      }

      $pre_count_unique_embedded_tables = count($unique_embedded_tables);
      $unique_embedded_tables = array_unique($unique_embedded_tables);
      $post_count_unique_embedded_tables = count($unique_embedded_tables);
      if (($pre_count_unique_embedded_tables > $post_count_unique_embedded_tables) && (count($unique_embedded_tables) > 1)) {
        $this->mongodbSortSeparate = TRUE;
      }
    }

    // RANGE.
    $this->mongodbSkip = NULL;
    $this->mongodbLimit = NULL;
    if (!empty($this->range)) {
      $this->mongodbSkip = (int) $this->range['start'];
      $this->mongodbLimit = (int) $this->range['length'];
    }
  }

  /**
   * Helper method setting the MongoDB projection part of the query.
   */
  protected function setMongodbProjection() {
    $this->mongodbProjection = [];

    foreach ($this->fields as $field) {
      if (isset($field['table']) && ($field['table'] != $this->mongodbBaseTable) && ($field['table'] != $this->mongodbBaseAlias)) {
        $embedded_table = NULL;
        if (isset($this->tables[$field['table']]['field'])) {
          $join_field_parts = explode('.', $this->tables[$field['table']]['field']);
          if (count($join_field_parts) > 1) {
            array_pop($join_field_parts);
            $embedded_table = implode('.', $join_field_parts);
          }
        }
        if (!empty($embedded_table)) {
          $field_name = $this->connection->escapeTable($field['table']) . '.' . $embedded_table . '.' . $this->connection->escapeField($field['field']);
        }
        else {
          $field_name = $this->connection->escapeTable($field['table']) . '.' . $this->connection->escapeField($field['field']);
        }
      }
      else {
        $field_name = $this->connection->escapeField($field['field']);
      }

      $field_alias = $field['alias'];
      if ($field_alias == $field_name) {
        $this->mongodbProjection[$field_name] = 1;
      }
      else {
        if (!in_array($field['table'], [$this->mongodbBaseTable, $this->mongodbBaseAlias])) {
          if (!isset($this->mongodbProjection[$field_alias])) {
            $this->mongodbProjection[$field_alias] = '$' . $field_name;
          }
          $field_alias = $field['table'] . '_' . $field_alias;
        }
        $this->mongodbProjection[$field_alias] = '$' . $field_name;
        // Selecting a field with an alias only works for aggregate queries.
        $this->mongodbUseAggregate = TRUE;
      }

      // Add expression fields to the projection.
      foreach ($this->expressions as $expression) {
        $alias = $expression['alias'];
        // Do not allow group by expressions to be added to the projection.
        if (isset($expression['expression'])) {
          preg_match('/(count|sum|avg|min|max)\((.*)\)/i', $expression['expression'], $wrong_matches);
          if (count($wrong_matches) > 0) {
            continue;
          }

          preg_match_all('/[a-z0-9_]+|[0-9]+|[*+-\/]{1}/i', $expression['expression'], $math_matches);
          $math_matches = reset($math_matches);
          if ((count($math_matches) == 3) && in_array($math_matches[1], ['*', '+', '-', '/'])) {
            $first_param = ctype_digit($math_matches[0]) || is_int($math_matches[0]) ? intval($math_matches[0]) : '$' . $math_matches[0];
            $second_param = ctype_digit($math_matches[2]) || is_int($math_matches[2]) ? intval($math_matches[2]) : '$' . $math_matches[2];

            switch ($math_matches[1]) {
              case '*':
                $this->mongodbProjection[$alias] = ['$multiply' => [$first_param, $second_param]];
                $this->mongodbUseAggregate = TRUE;
                break;

              case '/':
                $this->mongodbProjection[$alias] = ['$divide' => [$first_param, $second_param]];
                $this->mongodbUseAggregate = TRUE;
                break;

              case '+':
                $this->mongodbProjection[$alias] = ['$add' => [$first_param, $second_param]];
                $this->mongodbUseAggregate = TRUE;
                break;

              case '-':
                $this->mongodbProjection[$alias] = ['$subtract' => [$first_param, $second_param]];
                $this->mongodbUseAggregate = TRUE;
                break;

            }
          }
          else {
            $field = $expression['expression'];

            $first_dot = strpos($field, '.');
            if ($first_dot !== FALSE) {
              $first_field_part = substr($field, 0, $first_dot);
              if ($this->mongodbBaseTable == $first_field_part || $this->mongodbBaseAlias == $first_field_part) {
                $field = substr($field, $first_dot + 1);
              }
            }

            $table_fields = $this->connection->tableInformation()->getTableFields($this->mongodbBaseTable);
            if (!empty($table_fields) && is_array($table_fields) && in_array($field, array_keys($table_fields), TRUE)) {
              $this->mongodbProjection[$alias] = ['$' . $field];
            }
            elseif (isset($expression['expression']) && (!is_string($expression['expression']) || ($expression['expression'] != ''))) {
              // This is not a math expression. So, just add the expression.
              $this->mongodbProjection[$alias] = ['$literal' => $expression['expression']];
            }
          }
        }
      }
    }
  }

  /**
   * Helper method setting the MongoDB group by part of the query.
   */
  protected function setMongodbGroup() {
    $this->mongodbGroup = [];
    if ($this->group) {
      $_ids = [];
      foreach ($this->group as $alias => $field) {
        $dot_position = strrpos($this->getFieldName($field), '.');
        if ($dot_position != FALSE) {
          if (in_array(substr($this->getFieldName($field), 0, $dot_position), [
            $this->mongodbBaseTable,
            $this->mongodbBaseAlias,
          ])) {
            $field = substr($field, ($dot_position + 1));
          }
          else {
            // If the embedded table to use as base table variable is empty or
            // if the possible new field is more embedded then the current
            // variable then update the variable.
            $this->mongodbUnwind[] = '$' . substr($this->getFieldName($field), 0, $dot_position);
          }
        }

        // For MongoDB the alias field cannot contain dots.
        $alias = str_replace('.', '_', $alias);
        $_ids[$alias] = '$' . $this->getFieldName($field);

        $has_expression = FALSE;
        foreach ($this->expressions as $expression) {
          if (!empty($alias) && isset($expression['expression']) && stristr($expression['expression'], $alias) !== FALSE) {
            $has_expression = TRUE;
          }
        }

        // The alias or field name will automatically added to the query result.
        // But only remove the alias or field name if it is not used in an
        // expression.
        if (!$has_expression) {
          unset($this->fields[$alias]);
          unset($this->mongodbProjection[$alias]);
        }
      }

      $this->mongodbAddFieldsLast = [];
      if (!empty($_ids)) {
        $this->mongodbGroup['_id'] = $_ids;
        // The group by result is stored by MongoDB in the variable "_id".
        $this->mongodbRemoveIdField = FALSE;

        foreach ($_ids as $alias => $field) {
          $alias = $this->connection->escapeField($alias);
          $this->mongodbAddFieldsLast[$alias] = '$_id.' . $alias;
        }
      }

      // Add the MongoDB group fields.
      foreach ($this->mongodbGroupFields as $alias => $field) {
        $this->mongodbAddFieldsLast[$this->connection->escapeField($alias)] = '$' . $field;
      }
    }

    // Do not add expressions to a count query.
    if (!$this->mongodbCountQuery) {
      foreach ($this->mongodbGroupByOperation as $operation) {
        $this->setMongodbGroupHelper($operation['alias'], $operation['field'], $operation['operator']);
      }
      foreach ($this->expressions as $expression) {
        $alias = $expression['alias'];

        if (isset($expression['type'])) {
          switch ($expression['type']) {
            case 'count':
              $this->mongodbGroup[$alias] = ['$sum' => 1];
              break;

            case 'max':
              $this->mongodbGroup[$alias] = ['$max' => 1];
              break;

            case 'min':
              $this->mongodbGroup[$alias] = ['$min' => 1];
              break;

            case 'sum_multiply':
              $fields = $expression['fields'];
              foreach ($fields as &$field) {
                $base_table_length = strlen($this->mongodbBaseTable . '.');
                if (substr($field, 0, $base_table_length) == $this->mongodbBaseTable . '.') {
                  $field = substr($field, $base_table_length);
                }
                $base_alias_length = strlen($this->mongodbBaseAlias . '.');
                if (substr($field, 0, $base_alias_length) == $this->mongodbBaseAlias . '.') {
                  $field = substr($field, $base_alias_length);
                }
                $last_dot = strrpos($field, '.');
                if ($last_dot !== FALSE) {
                  $embedded_table = substr($field, 0, $last_dot);
                  if (!in_array($embedded_table, $this->mongodbUnwind, TRUE)) {
                    $this->mongodbUnwind[] = '$' . $embedded_table;
                  }
                }
                $field = '$' . $field;
              }
              if (!empty($expression['values'])) {
                foreach ($expression['values'] as $value) {
                  $fields[] = ['$literal' => $value];
                }
              }
              $this->mongodbGroup[$alias] = ['$sum' => ['$multiply' => $fields]];
              break;

          }
        }
        else {
          preg_match('/(count|sum|avg|min|max)\((.*)\)/i', $expression['expression'], $matches);
          if (count($matches) > 1) {
            $operator = strtoupper($matches[1]);

            // Get the basic field. Remove table or alias name from the field.
            $first_dot = strpos($matches[2], '.');
            if ($first_dot !== FALSE) {
              $first_field_part = substr($matches[2], 0, $first_dot);
              if ($this->mongodbBaseTable == $first_field_part || $this->mongodbBaseAlias == $first_field_part) {
                $field = substr($matches[2], $first_dot + 1);
              }
              else {
                // The first part can also be of an embedded table. Do not
                // remove those.
                $field = $matches[2];
              }
            }
            else {
              $field = $matches[2];
            }

            $this->setMongodbGroupHelper($alias, $field, $operator);
          }
        }
      }
      if (!empty($this->mongodbGroup) && !isset($this->mongodbGroup['_id'])) {
        $this->mongodbGroup['_id'] = [];
      }
    }
  }

  /**
   * Helper method getting the field name for its alias or its own name.
   *
   * @param string $alias
   *   The alias to be used for the group by.
   * @param string $field
   *   The field name to be used for the group by.
   * @param string $operator
   *   The operator to be used for the group by.
   */
  protected function setMongodbGroupHelper($alias, $field, $operator) {
    // @todo The embedded table can be more then one level deep.
    // Check if the field is in an embedded table.
    $last_dot = strrpos($field, '.');
    if ($last_dot !== FALSE) {
      $embedded_table = substr($field, 0, $last_dot);
      if (!in_array($embedded_table, $this->mongodbUnwind, TRUE)) {
        $this->mongodbUnwind[] = '$' . $embedded_table;
      }
    }

    switch (strtoupper($operator)) {
      case 'COUNT':
        $this->mongodbGroup[$alias] = ['$sum' => 1];
        break;

      case 'SUM':
        $this->mongodbGroup[$alias] = ['$sum' => '$' . $field];
        break;

      case 'AVG':
        $this->mongodbGroup[$alias] = ['$avg' => '$' . $field];
        break;

      case 'MIN':
        $this->mongodbGroup[$alias] = ['$min' => '$' . $field];
        break;

      case 'MAX':
        $this->mongodbGroup[$alias] = ['$max' => '$' . $field];
        break;

    }

    $this->mongodbRemoveIdField = FALSE;
  }

  /**
   * Helper method for getting the field name for its alias or its own name.
   *
   * @param string $name_or_alias
   *   The name_or_alias to used for getting the field name.
   *
   * @return string
   *   The field name.
   */
  protected function getFieldName($name_or_alias) {
    if (isset($this->fields[$name_or_alias]['field'])) {
      return $this->fields[$name_or_alias]['field'];
    }
    return $name_or_alias;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    // Let the method execute() return the query as its string value.
    $this->mongodbQueryStringValue = TRUE;

    return $this->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getArguments(?PlaceholderInterface $queryPlaceholder = NULL) {
    return [];
  }

}
