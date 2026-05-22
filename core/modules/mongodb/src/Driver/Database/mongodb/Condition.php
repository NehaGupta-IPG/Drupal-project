<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Daffie\SqlLikeToRegularExpression;
use Drupal\Core\Database\Connection as DatabaseConnection;
use Drupal\Core\Database\InvalidQueryException;
use Drupal\Core\Database\Query\Condition as QueryCondition;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Database\Query\PlaceholderInterface;
use Drupal\Core\Database\Query\SelectInterface;
use MongoDB\BSON\Regex;

// cspell:ignore datedate datestring fieldcompare wsod

/**
 * MongoDB implementation of \Drupal\Core\Database\Query\Condition.
 */
class Condition extends QueryCondition {

  /**
   * The MongoDB condition array for non-aggregate queries.
   *
   * @var array
   */
  protected $mongodbVersion = [];

  /**
   * The MongoDB condition array for non-aggregate queries.
   *
   * @var array
   */
  protected $mongodbAggregateVersion = [];

  /**
   * The alias name of the base table of the query.
   *
   * @var string
   */
  protected $mongodbBaseAlias;

  /**
   * The name of the base table of the query.
   *
   * @var string
   */
  protected $mongodbBaseTable;

  /**
   * The condition is part of a query join.
   *
   * @var bool
   */
  protected bool $mongodbJoinCondition;

  /**
   * An array of embedded table names used in the condition.
   *
   * @var array
   */
  protected $mongodbEmbeddedTables = [];

  /**
   * An array of embedded table names.
   *
   * Used in the condition with the MongoDB $elemMatch operator.
   *
   * @var array
   */
  protected $mongodbElemMatchEmbeddedTables = [];

  /**
   * An array of embedded table names used in the condition to be unwound.
   *
   * @var array
   */
  protected $mongodbUnwoundTables = [];

  /**
   * An array of meta data.
   *
   * Used with the views module. Variables in the condition can then be
   * substitutions.
   *
   * @var array
   */
  protected $alterMetaData = [];

  /**
   * The embedded table name for which to create the projection condition.
   *
   * @var ?string
   */
  protected $mongodbEmbeddedTableProjection = NULL;

  /**
   * Disable the use of $elemMatch in the condition.
   *
   * @var bool
   */
  protected $useElementMatch = TRUE;

  /**
   * Use the condition to create a MongoDB projection condition.
   *
   * MongoDB will return all embedded table rows with a normal query for an
   * embedded table. MongoDB has the possibility for reducing the number of
   * embedded table rows with a projection condition. See:
   * https://docs.mongodb.com/manual/reference/operator/projection/elemMatch/.
   *
   * param string $table
   *   The name of the embedded table for which to create the projection
   *   condition.
   */
  public function setMongodbEmbeddedTableProjection($table) {
    $this->mongodbEmbeddedTableProjection = $table;
  }

  /**
   * Set MongoDB base table name.
   *
   * @param string $table
   *   The name of the table to be used as the base table of the query.
   */
  public function setMongodbBaseTable($table) {
    $this->mongodbBaseTable = $table;
  }

  /**
   * Set MongoDB base table alias.
   *
   * @param string $alias
   *   The alias of the table to be used as the base table of the query.
   */
  public function setMongodbBaseAlias($alias) {
    $this->mongodbBaseAlias = $alias;
  }

  /**
   * Sets the variable join condition.
   *
   * @param bool $join
   *   (optional) The boolean value to set. Defaults to TRUE.
   */
  public function setMongodbJoinCondition(bool $join = TRUE): void {
    $this->mongodbJoinCondition = $join;
    // We shall need to recompile the condition.
    $this->changed = TRUE;
  }

  /**
   * Set meta data for the condition.
   *
   * @param array $meta_data
   *   The meta data.
   */
  public function setMetaData($meta_data) {
    $this->alterMetaData = $meta_data;
  }

  /**
   * Enable/disable the use of $elemMatch in the condition.
   *
   * @param bool $set
   *   The boolean value to be set.
   */
  public function useElementMatch(bool $set): void {
    $this->useElementMatch = $set;
  }

  /**
   * Constructs a Condition object.
   *
   * This method is the same as its parent class. Only without PHPCS will throw
   * the following error: PHP4 style constructors are not allowed; use
   * "__construct()" instead.
   *
   * @param string $conjunction
   *   The operator to use to combine conditions: 'AND' or 'OR'.
   */
  public function __construct($conjunction) {
    parent::__construct($conjunction);
    $this->mongodbJoinCondition = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function condition($field, $value = NULL, $operator = '=') {
    if (empty($operator)) {
      $operator = '=';
    }
    if (empty($value) && is_array($value)) {
      throw new InvalidQueryException(sprintf("Query condition '%s %s ()' cannot be empty.", $field, $operator));
    }
    if (is_array($value) && in_array($operator, ['=', '<', '>', '<=', '>=', 'IS NULL', 'IS NOT NULL'], TRUE)) {
      if (count($value) > 1) {
        $value = implode(', ', $value);
        throw new InvalidQueryException(sprintf("Query condition '%s %s %s' must have an array compatible operator.", $field, $operator, $value));
      }
      else {
        throw new InvalidQueryException('Calling ' . __METHOD__ . '() without an array compatible operator is not supported. See https://www.drupal.org/node/3350985');
      }
    }

    // Merge conditions when possible.
    $existing = FALSE;
    foreach ($this->conditions as &$condition) {
      if (isset($condition['field']) && is_string($condition['field']) && is_string($field) && ($condition['field'] == $field) && isset($condition['operator']) && ($condition['operator'] == $operator)) {
        if (strtoupper($operator) == 'IN') {
          $condition['value'] = array_merge($condition['value'], $value);
          $existing = TRUE;
        }
      }
    }

    if (!$existing) {
      $this->conditions[] = [
        'field' => $field,
        'value' => $value,
        'operator' => $operator,
      ];
    }

    $this->changed = TRUE;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function where($snippet, $args = []) {
    throw new MongodbSQLException('MongoDB does not support methods with SQL string input. Use the method Condition::condition() instead of Condition::where().');
  }

  /**
   * {@inheritdoc}
   */
  public function alwaysFalse() {
    return $this->condition('_id', NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function compile(DatabaseConnection $connection, PlaceholderInterface $queryPlaceholder) {
    // Re-compile if this condition changed or if we are compiled against a
    // different query placeholder object.
    if ($this->changed || isset($this->queryPlaceholderIdentifier) && ($this->queryPlaceholderIdentifier != $queryPlaceholder->uniqueIdentifier())) {
      $this->queryPlaceholderIdentifier = $queryPlaceholder->uniqueIdentifier();

      $condition_fragments = [];
      $condition_aggregate_fragments = [];
      $embedded_tables_condition_fragments = [];
      $expr_condition_fragments = [];
      $expr_condition_aggregate_fragments = [];
      $arguments = [];

      $conditions = $this->conditions;
      $conjunction = strtoupper($conditions['#conjunction']);
      unset($conditions['#conjunction']);

      // The embedded tables variable holds the number of conditions per
      // embedded table keyed by the embedded table.
      $this->mongodbEmbeddedTables = [];
      if ($conjunction == 'AND') {
        if (!$this->mongodbJoinCondition) {
          // Get the number of conditions per embedded table.
          foreach ($conditions as $condition) {
            if (is_string($condition['field'])) {
              $last_dot = strrpos($condition['field'], '.');
              if ($last_dot !== FALSE) {
                // With selecting which embedded table to create a MongoDB
                // $elemMatch for, make it as deep as possible.
                $embedded_table = substr($condition['field'], 0, $last_dot);
                // @todo Remove the embedded table part if it is the base table
                // or the base table alias.
                if (in_array($embedded_table, [
                  $this->mongodbBaseTable,
                  $this->mongodbBaseAlias,
                ])) {
                  $condition['field'] = substr($condition['field'], $last_dot + 1);
                }
                else {
                  if (!isset($this->mongodbEmbeddedTables[$embedded_table])) {
                    $this->mongodbEmbeddedTables[$embedded_table] = 1;
                  }
                  else {
                    $this->mongodbEmbeddedTables[$embedded_table]++;
                  }
                }
              }
            }
          }
          // Unwound (embedded) tables are no longer arrays. Therefor the
          // condition operator $elemMatch can no longer be used, because it
          // only works on arrays.
          if (!empty($this->mongodbEmbeddedTables) && !empty($this->mongodbUnwoundTables)) {
            foreach ($this->mongodbEmbeddedTables as $mongodbEmbeddedTableName => $mongodbEmbeddedTableCount) {
              if (in_array($mongodbEmbeddedTableName, $this->mongodbUnwoundTables, TRUE)) {
                unset($this->mongodbEmbeddedTables[$mongodbEmbeddedTableName]);
              }
            }
          }
          if (!empty($this->mongodbEmbeddedTables)) {
            // For embedded tables with multiple conditions change those to
            // MongoDB "$elemMatch" on the embedded table. At this stage remove
            // the embedded table from the field value and add the embedded
            // table value.
            foreach ($conditions as &$condition) {
              if (is_string($condition['field'])) {
                $last_dot = strrpos($condition['field'], '.');
                if ($last_dot !== FALSE) {
                  $embedded_table = substr($condition['field'], 0, $last_dot);
                  if (isset($this->mongodbEmbeddedTables[$embedded_table]) && (intval($this->mongodbEmbeddedTables[$embedded_table]) > 1) || !empty($this->mongodbEmbeddedTableProjection)) {
                    $condition['field'] = substr($condition['field'], $last_dot + 1);
                    $condition['embedded_table'] = $embedded_table;
                  }
                }
              }
            }
            // Make sure that the variable $condition is unset.
            unset($condition);
          }
        }
      }

      foreach ($conditions as $condition) {
        $condition_fragment = NULL;
        $condition_fragment2 = NULL;
        $condition_aggregate_fragment = NULL;
        $condition_aggregate_fragment2 = NULL;

        if (empty($condition['operator'])) {
          throw new MongodbSQLException('The condition part of the query contains an SQL string. And you cannot run a SQL query against a MongoDB database. $condition: ' . print_r($condition, TRUE));
        }
        else {
          // It's a structured condition, so parse it out accordingly.
          // Note that $condition['field'] will only be an object for a
          // dependent DatabaseCondition object, not for a dependent subquery.
          if ($condition['field'] instanceof ConditionInterface) {
            $condition['field']->setMongodbBaseTable($this->mongodbBaseTable);
            $condition['field']->setMongodbBaseAlias($this->mongodbBaseAlias);
            $condition['field']->setMongodbJoinCondition($this->mongodbJoinCondition);
            $condition['field']->setMetaData($this->alterMetaData);
            $condition['field']->setUnwoundTables($this->mongodbUnwoundTables);
            if (!empty($this->mongodbEmbeddedTableProjection)) {
              $condition['field']->setMongodbEmbeddedTableProjection($this->mongodbEmbeddedTableProjection);
            }
            // Compile the sub-condition recursively and add it to the list.
            $condition['field']->compile($connection, $queryPlaceholder);
            $condition_fragments[] = $condition['field']->toMongoArray();
            $condition_aggregate_fragments[] = $condition['field']->toMongoAggregateArray();
            $this->mongodbElemMatchEmbeddedTables += $condition['field']->getElemMatchEmbeddedTables();
            $arguments += $condition['field']->arguments();
          }
          else {
            // For simplicity, we treat all operators as the same data
            // structure. In the typical degenerate case, this won't get
            // changed.
            $operator_defaults = [
              'prefix' => '',
              'delimiter' => '',
              'operator' => $condition['operator'],
            ];
            // Remove potentially dangerous characters.
            // If something passed in an invalid character stop early, so we
            // don't rely on a broken SQL statement when we would just replace
            // those characters.
            if (stripos($condition['operator'], 'UNION') !== FALSE || strpbrk($condition['operator'], '[-\'"();') !== FALSE) {
              $this->changed = TRUE;
              $this->arguments = [];
              // Provide a string which will result into an empty query result.
              $this->stringVersion = '( AND 1 = 0 )';

              // Conceptually throwing an exception caused by user input is bad
              // as you result into a WSOD, which depending on your webserver
              // configuration can result into the assumption that your site is
              // broken.
              // On top of that the database API relies on __toString() which
              // does not allow to throw exceptions.
              trigger_error('Invalid characters in query operator: ' . $condition['operator'], E_USER_ERROR);
              return;
            }

            // Get the operator from the connection class only. It is also used
            // by the Select class.
            $operator = $connection->mapConditionOperator($condition['operator']);
            $operator += $operator_defaults;

            // Remove base table or base alias from the field name (including
            // the connection dot).
            if (!empty($this->mongodbBaseTable)) {
              $condition['field'] = preg_replace('/^' . $this->mongodbBaseTable . '\./', '', $condition['field']);
              if (isset($condition['field2'])) {
                $condition['field2'] = preg_replace('/^' . $this->mongodbBaseTable . '\./', '', $condition['field2']);
              }
            }
            if (!empty($this->mongodbBaseAlias)) {
              $condition['field'] = preg_replace('/^' . $this->mongodbBaseAlias . '\./', '', $condition['field']);
              if (isset($condition['field2'])) {
                $condition['field2'] = preg_replace('/^' . $this->mongodbBaseAlias . '\./', '', $condition['field2']);
              }
            }

            if (isset($condition['field2'])) {
              $pattern = '\$toInt|\$ifNull';
              // If the field name contains the string "$toInt" or "$ifNull" the
              // field contains a special query.
              if (preg_match("/$pattern/", $condition['field'])) {
                $field = unserialize($condition['field']);
              }
              else {
                $field = '$' . $connection->escapeField($condition['field']);
              }
              if (preg_match("/$pattern/", $condition['field2'])) {
                $field2 = unserialize($condition['field2']);
              }
              else {
                $field2 = '$' . $connection->escapeField($condition['field2']);
              }
              if ($this->mongodbJoinCondition) {
                $condition_fragment = $condition_aggregate_fragment = [
                  '$expr' => [
                    $operator['mongodb_operator'] => [
                      $field,
                      $field2,
                    ],
                  ],
                ];
              }
              else {
                $condition_fragment = $condition_aggregate_fragment = [$connection->escapeField($condition['field']) => [$operator['mongodb_operator'] => '$' . $connection->escapeField($condition['field2'])]];
                if (!empty($condition['embedded_table'])) {
                  $condition_aggregate_fragment = [$connection->escapeField($condition['embedded_table'] . '.' . $condition['field']) => [$operator['mongodb_operator'] => '$' . $connection->escapeField($condition['embedded_table'] . '.' . $condition['field2'])]];
                }
              }
            }
            else {
              $placeholders = [];
              if ($condition['value'] instanceof SelectInterface) {
                $condition['value']->compile($connection, $queryPlaceholder);
                $placeholders[] = (string) $condition['value'];
                $arguments += $condition['value']->arguments();
              }
              // We assume that if there is a delimiter, then the value is an
              // array. If not, it is a scalar. For simplicity, we first
              // convert up to an array so that we can build the placeholders
              // in the same way.
              elseif (!$operator['delimiter'] && !is_array($condition['value'])) {
                $condition['value'] = [$condition['value']];
              }
              // For MongoDB the condition value can be an empty array.
              elseif (!$operator['delimiter'] && is_array($condition['value']) && empty($condition['value'])) {
                $condition['value'] = [$condition['value']];
              }

              if (isset($this->alterMetaData['views_substitutions']) && is_array($this->alterMetaData['views_substitutions'])) {
                foreach ($this->alterMetaData['views_substitutions'] as $views_substitution_key => $views_substitution_value) {
                  if (is_array($condition['value'])) {
                    foreach ($condition['value'] as &$value) {
                      if ($value === $views_substitution_key) {
                        $value = $views_substitution_value;
                      }
                    }
                    unset($value);
                  }
                  elseif ($condition['value'] === $views_substitution_key) {
                    $condition['value'] = $views_substitution_value;
                  }
                  if ($condition['field'] === $views_substitution_key) {
                    $condition['expression'] = TRUE;
                    $condition['field'] = $views_substitution_value;
                  }
                }
              }

              switch ($operator['operator']) {
                case '=':
                case '<':
                case '>':
                case '<=':
                case '>=':
                case '<>':
                case '!=':
                  if (reset($condition['value']) === NULL) {
                    // If we have the condition value "NULL" then make sure we
                    // select nothing. Yes, this is a hack!
                    $condition_fragment = $condition_aggregate_fragment = [$connection->escapeField($condition['field']) => ['$gt' => 1]];
                    $condition_fragment2 = $condition_aggregate_fragment2 = [$connection->escapeField($condition['field']) => ['$lt' => 1]];
                    if (!empty($condition['embedded_table'])) {
                      $condition_aggregate_fragment = [$connection->escapeField($condition['embedded_table'] . '.' . $condition['field']) => ['$gt' => 1]];
                      $condition_aggregate_fragment2 = [$connection->escapeField($condition['embedded_table'] . '.' . $condition['field']) => ['$lt' => 1]];
                    }
                  }
                  elseif (isset($condition['expression']) && $condition['expression']) {
                    $condition_fragment = $condition_aggregate_fragment = [
                      '$expr' => [
                        $operator['mongodb_operator'] => [
                          (is_string($condition['field']) ? $connection->escapeField($condition['field']) : $condition['field']),
                          reset($condition['value']),
                        ],
                      ],
                    ];
                    if (!empty($condition['embedded_table'])) {
                      $condition_aggregate_fragment = [
                        '$expr' => [
                          $operator['mongodb_operator'] => [
                            $connection->escapeField($condition['embedded_table'] . '.' . $condition['field']),
                            reset($condition['value']),
                          ],
                        ],
                      ];
                    }
                  }
                  else {
                    if ($this->mongodbJoinCondition) {
                      $condition_fragment = $condition_aggregate_fragment = [
                        '$expr' => [
                          $operator['mongodb_operator'] => [
                            '$' . $connection->escapeField($condition['field']),
                            reset($condition['value']),
                          ],
                        ],
                      ];
                      if (!empty($condition['embedded_table'])) {
                        $condition_aggregate_fragment = [
                          '$expr' => [
                            $operator['mongodb_operator'] => [
                              '$' . $connection->escapeField($condition['embedded_table'] . '.' . $connection->escapeField($condition['field'])),
                              reset($condition['value']),
                            ],
                          ],
                        ];
                      }
                    }
                    else {
                      $action = [$operator['mongodb_operator'] => reset($condition['value'])];
                      $condition_fragment = $condition_aggregate_fragment = [$connection->escapeField($condition['field']) => $action];
                      if (!empty($condition['embedded_table'])) {
                        $condition_aggregate_fragment = [$connection->escapeField($condition['embedded_table'] . '.' . $condition['field']) => $action];
                      }
                    }
                  }
                  break;

                case 'IN':
                case 'NOT IN':
                  // MongoDB does not like array key values with '$in' and
                  // '$nin' operators.
                  if ($this->mongodbJoinCondition) {
                    $condition_fragment = $condition_aggregate_fragment = [
                      '$expr' => [
                        $operator['mongodb_operator'] => [
                          '$' . $connection->escapeField($condition['field']),
                          array_values($condition['value']),
                        ],
                      ],
                    ];
                    if (!empty($condition['embedded_table'])) {
                      $condition_aggregate_fragment = [
                        '$expr' => [
                          $operator['mongodb_operator'] => [
                            '$' . $connection->escapeField($condition['embedded_table'] . '.' . $connection->escapeField($condition['field'])),
                            array_values($condition['value']),
                          ],
                        ],
                      ];
                    }
                  }
                  else {
                    $action = [$operator['mongodb_operator'] => array_values($condition['value'])];
                    $condition_fragment = $condition_aggregate_fragment = [$connection->escapeField($condition['field']) => $action];
                    if (!empty($condition['embedded_table'])) {
                      $condition_aggregate_fragment = [$connection->escapeField($condition['embedded_table'] . '.' . $condition['field']) => $action];
                    }
                  }
                  break;

                case 'BETWEEN':
                  $top_action = ['$lte' => next($condition['value'])];
                  $top = $aggregate_top = [$connection->escapeField($condition['field']) => $top_action];
                  if (!empty($condition['embedded_table'])) {
                    $aggregate_top = [$connection->escapeField($condition['embedded_table'] . '.' . $condition['field']) => $top_action];
                  }

                  $bottom_action = ['$gte' => reset($condition['value'])];
                  $bottom = $aggregate_bottom = [$connection->escapeField($condition['field']) => $bottom_action];
                  if (!empty($condition['embedded_table'])) {
                    $aggregate_bottom = [$connection->escapeField($condition['embedded_table'] . '.' . $condition['field']) => $bottom_action];
                  }

                  $condition_fragment = ['$and' => [$bottom, $top]];
                  $condition_aggregate_fragment = [
                    '$and' => [
                      $aggregate_bottom,
                      $aggregate_top,
                    ],
                  ];
                  break;

                case 'NOT BETWEEN':
                  $top_action = ['$gt' => next($condition['value'])];
                  $top = $aggregate_top = [$connection->escapeField($condition['field']) => $top_action];
                  if (!empty($condition['embedded_table'])) {
                    $aggregate_top = [$connection->escapeField($condition['embedded_table'] . '.' . $condition['field']) => $top_action];
                  }

                  $bottom_action = ['$lt' => reset($condition['value'])];
                  $bottom = $aggregate_bottom = [$connection->escapeField($condition['field']) => $bottom_action];
                  if (!empty($condition['embedded_table'])) {
                    $aggregate_bottom = [$connection->escapeField($condition['embedded_table'] . '.' . $condition['field']) => $bottom_action];
                  }

                  $condition_fragment = ['$or' => [$bottom, $top]];
                  $condition_aggregate_fragment = [
                    '$or' => [
                      $aggregate_bottom,
                      $aggregate_top,
                    ],
                  ];
                  break;

                case 'IS NULL':
                  // In this MongoDB driver are fields with the value null not
                  // set.
                  $action = ['$exists' => FALSE];
                  $condition_fragment = $condition_aggregate_fragment = [$connection->escapeField($condition['field']) => $action];
                  if (!empty($condition['embedded_table'])) {
                    $condition_aggregate_fragment = [$connection->escapeField($condition['embedded_table'] . '.' . $condition['field']) => $action];
                  }
                  break;

                case 'IS NOT NULL':
                  // In this MongoDB driver are fields with the value null not
                  // set.
                  $action = ['$exists' => TRUE];
                  $condition_fragment = $condition_aggregate_fragment = [$connection->escapeField($condition['field']) => $action];
                  if (!empty($condition['embedded_table'])) {
                    $condition_aggregate_fragment = [$connection->escapeField($condition['embedded_table'] . '.' . $condition['field']) => $action];
                  }
                  break;

                case 'LIKE':
                case 'LIKE BINARY':
                case 'NOT LIKE':
                case 'REGEXP':
                case 'NOT REGEXP':
                  $pattern = reset($condition['value']);
                  if ($operator['operator'] == 'LIKE' || $operator['operator'] == 'NOT LIKE' || $operator['operator'] == 'LIKE BINARY') {
                    // MongoDB does not support SQL LIKE statements. They must
                    // be changed to regular expression.
                    $pattern = SqlLikeToRegularExpression::convert($pattern);
                  }

                  // The default regular expression is case insensitive.
                  if ($operator['operator'] == 'LIKE BINARY') {
                    $regex = new Regex($pattern, '');
                  }
                  else {
                    $regex = new Regex($pattern, 'i');
                  }

                  if ($operator['operator'] == 'NOT LIKE' || $operator['operator'] == 'NOT REGEXP') {
                    $action = ['$not' => $regex];
                    $condition_fragment = $condition_aggregate_fragment = [$connection->escapeField($condition['field']) => $action];
                    if (!empty($condition['embedded_table'])) {
                      $condition_aggregate_fragment = [$connection->escapeField($condition['embedded_table'] . '.' . $condition['field']) => $action];
                    }
                  }
                  else {
                    $action = $regex;
                    $condition_fragment = $condition_aggregate_fragment = [$connection->escapeField($condition['field']) => $action];
                    if (!empty($condition['embedded_table'])) {
                      $condition_aggregate_fragment = [$connection->escapeField($condition['embedded_table'] . '.' . $condition['field']) => $action];
                    }
                  }
                  break;

                case 'IN BINARY':
                case 'IN NOT BINARY':
                  $patterns = [];
                  foreach ($condition['value'] as $pattern) {
                    $patterns[] = SqlLikeToRegularExpression::convert($pattern);
                  }
                  $regexes = [];
                  foreach ($patterns as $pattern) {
                    if ($operator['operator'] == 'IN BINARY') {
                      $regexes[] = new Regex($pattern, '');
                    }
                    else {
                      $regexes[] = new Regex($pattern, 'i');
                    }
                  }
                  $action = ['$in' => $regexes];
                  $condition_fragment = $condition_aggregate_fragment = [$connection->escapeField($condition['field']) => $action];
                  if (!empty($condition['embedded_table'])) {
                    $condition_aggregate_fragment = [$connection->escapeField($condition['embedded_table'] . '.' . $condition['field']) => $action];
                  }
                  break;

                case 'DATEDATE':
                  // Add the embedded table data back to the field. The embedded
                  // table data will be unwound by the MongoDB query.
                  if (!empty($condition['embedded_table'])) {
                    $condition['field'] = $condition['embedded_table'] . '.' . $condition['field'];
                  }

                  // Get the comparison operator for this condition.
                  $operator = (!empty($condition['value']['operator']) ? $connection->mapConditionOperator($condition['value']['operator']) : NULL);
                  $operator = (!empty($operator['mongodb_operator']) ? $operator['mongodb_operator'] : '$eq');

                  $date_to_string = [
                    '$dateToString' => [
                      'format' => $condition['value']['format'],
                      'date' => '$' . $connection->escapeField($condition['field']),
                    ],
                  ];
                  if (!empty($condition['value']['timezone'])) {
                    $date_to_string['$dateToString']['timezone'] = $condition['value']['timezone'];
                  }

                  $condition_fragment = $condition_aggregate_fragment = [
                    '$expr' => [
                      '$and' => [
                        [
                          $operator => [
                            $date_to_string,
                            $condition['value']['value'],
                          ],
                        ],
                        [
                          '$ifNull' => [
                            '$' . $connection->escapeField($condition['field']),
                            FALSE,
                          ],
                        ],
                      ],
                    ],
                  ];
                  break;

                case 'DATESTRING':
                  // The DATESTRING operator is created for the datetime module.
                  // Add the embedded table data back to the field. The embedded
                  // table data will be unwound by the MongoDB query.
                  if (!empty($condition['embedded_table'])) {
                    $condition['field'] = $condition['embedded_table'] . '.' . $condition['field'];
                  }

                  // Get the comparison operator for this condition.
                  $operator = (!empty($condition['value']['operator']) ? $connection->mapConditionOperator($condition['value']['operator']) : NULL);
                  $operator = (!empty($operator['mongodb_operator']) ? $operator['mongodb_operator'] : '$eq');

                  $date_to_string = [
                    '$dateToString' => [
                      'format' => $condition['value']['format'],
                      'date' => [
                        '$dateFromString' => [
                          'dateString' => '$' . $connection->escapeField($condition['field']),
                        ],
                      ],
                    ],
                  ];
                  if (!empty($condition['value']['timezone'])) {
                    $date_to_string['$dateToString']['timezone'] = $condition['value']['timezone'];
                  }

                  $condition_fragment = $condition_aggregate_fragment = [
                    '$expr' => [
                      '$and' => [
                        [
                          $operator => [
                            $date_to_string,
                            $condition['value']['value'],
                          ],
                        ],
                        [
                          '$ifNull' => [
                            '$' . $connection->escapeField($condition['field']),
                            FALSE,
                          ],
                        ],
                      ],
                    ],
                  ];
                  break;

                case 'FIELDCOMPARE':
                  // Add the embedded table data back to the field. The embedded
                  // table data will be unwound by the MongoDB query.
                  if (!empty($condition['embedded_table'])) {
                    $condition['field'] = $condition['embedded_table'] . '.' . $condition['field'];
                  }

                  // Get the comparison operator for this condition.
                  $operator = (!empty($condition['value']['operator']) ? $connection->mapConditionOperator($condition['value']['operator']) : NULL);
                  $operator = (!empty($operator['mongodb_operator']) ? $operator['mongodb_operator'] : '$eq');

                  $condition_fragment = $condition_aggregate_fragment = [
                    '$expr' => [
                      $operator => [
                        '$' . $connection->escapeField($condition['field']),
                        '$' . $connection->escapeField($condition['value']['field']),
                      ],
                    ],
                  ];
                  break;

                case 'ARRAY_EMPTY':
                case 'ARRAY_NOT_EMPTY':
                  $operator = ($operator['operator'] == 'ARRAY_EMPTY') ? '$eq' : '$ne';
                  $condition_fragment = $condition_aggregate_fragment = [$connection->escapeField($condition['field']) => [$operator => []]];
                  if (!empty($condition['embedded_table'])) {
                    $condition_fragment = $condition_aggregate_fragment = [$connection->escapeField($condition['embedded_table'] . '.' . $condition['field']) => [$operator => []]];
                  }
                  break;

                default:
                  $condition_fragment = $condition_aggregate_fragment = ' (' . $connection->escapeField($condition['field']) . ' ' . $operator['operator'] . ' ' . $operator['prefix'] . implode($operator['delimiter'], $placeholders) . ') ';
              }
            }
          }
        }

        // @todo Test if we can remove the whole $expr_condition_fragments and
        // $expr_condition_aggregate_fragments!
        // Conditions that start with $expr must be combined differently.
        if (in_array($condition['operator'], ['DATEDATE', 'DATESTRING'])) {
          $expr_condition_fragments[] = reset($condition_fragment);
          $expr_condition_aggregate_fragments[] = reset($condition_aggregate_fragment);
        }
        else {
          // Multiple conditions on the same embedded table should be combined.
          if (!empty($condition['embedded_table'])) {
            $embedded_table = $condition['embedded_table'];
            if (!empty($condition_fragment)) {
              $embedded_table_field_name = key($condition_fragment);
              $embedded_table_field_operator_and_value = current($condition_fragment);
              if ($embedded_table_field_operator_and_value instanceof Regex) {
                $embedded_table_field_operator = '$eq';
              }
              elseif (!is_array($embedded_table_field_operator_and_value)) {
                $embedded_table_field_operator = '$eq';
              }
              else {
                $embedded_table_field_operator = key($embedded_table_field_operator_and_value);
              }

              if (!isset($embedded_tables_condition_fragments[$embedded_table][$embedded_table_field_name])) {
                // There is no query for the field name. We can just add one.
                $embedded_tables_condition_fragments[$embedded_table][$embedded_table_field_name] = $embedded_table_field_operator_and_value;
              }
              else {
                $used_embedded_table_field_operator_and_values = $embedded_tables_condition_fragments[$embedded_table][$embedded_table_field_name];
                if (is_array($used_embedded_table_field_operator_and_values) && in_array($embedded_table_field_operator, array_keys($used_embedded_table_field_operator_and_values), TRUE)) {
                  switch ($embedded_table_field_operator) {
                    case '$eq':
                      // @todo The next part is wrong.
                      if (!isset($embedded_tables_condition_fragments[$embedded_table][$embedded_table_field_name]['$in'])) {
                        // Change the field operator to $in and put the expected
                        // value in an array.
                        $embedded_tables_condition_fragments[$embedded_table][$embedded_table_field_name]['$in'] = [reset($embedded_table_field_operator_and_value)];
                      }
                      else {
                        throw new MongodbSQLException('You cannot use multiple duplicate operators to the same field (' . key($condition_fragment) . ') on the same embedded table (' . $embedded_table . '). The MongoDB multiple called operator is: "' . key(current($condition_fragment)) . '".');
                      }
                      break;

                    case '$lt':
                    case '$lte':
                    case '$gt':
                    case '$gte':
                    case '$in':
                    case '$nin':
                    default:
                      // For all these cases can we do something, so that we do
                      // not have to throw an exception.
                      throw new MongodbSQLException('You cannot use multiple duplicate operators to the same field (' . key($condition_fragment) . ') on the same embedded table (' . $embedded_table . '). The MongoDB multiple called operator is: "' . key(current($condition_fragment)) . '".');
                  }
                }
                elseif (is_array($used_embedded_table_field_operator_and_values)) {
                  // The operator is not being used for the field name.
                  if (!is_array($embedded_table_field_operator_and_value) && ($embedded_table_field_operator_and_value instanceof Regex)) {
                    $embedded_table_field_operator_and_value = ['$regex' => $embedded_table_field_operator_and_value];
                  }
                  $new_values = array_merge($used_embedded_table_field_operator_and_values, $embedded_table_field_operator_and_value);
                  $embedded_tables_condition_fragments[$embedded_table][$embedded_table_field_name] = $new_values;
                }
              }
            }
            if (!empty($condition_fragment2)) {
              $embedded_tables_condition_fragments[$embedded_table][key($condition_fragment2)] = current($condition_fragment2);
            }
          }
          else {
            // All other conditions.
            if (!empty($condition_fragment)) {
              $condition_fragments[] = $condition_fragment;
            }
            if (!empty($condition_fragment2)) {
              $condition_fragments[] = $condition_fragment2;
            }
          }
          if (!empty($condition_aggregate_fragment)) {
            $condition_aggregate_fragments[] = $condition_aggregate_fragment;
          }
          if (!empty($condition_aggregate_fragment2)) {
            $condition_aggregate_fragments[] = $condition_aggregate_fragment2;
          }
        }
      }

      $this->changed = FALSE;
      $this->mongodbVersion = [];
      $this->mongodbAggregateVersion = [];

      $number_of_condition_fragments = count($condition_fragments);
      foreach ($condition_fragments as $condition_fragment) {
        if (($conjunction == 'OR') && ($number_of_condition_fragments > 1)) {
          $this->mongodbVersion['$or'][] = $condition_fragment;
        }
        elseif (($conjunction == 'AND') && (($number_of_condition_fragments > 1) || !empty($embedded_tables_condition_fragments))) {
          $this->mongodbVersion['$and'][] = $condition_fragment;
        }
        else {
          $this->mongodbVersion = $condition_fragment;
        }
      }

      $number_of_condition_aggregate_fragments = count($condition_aggregate_fragments);
      foreach ($condition_aggregate_fragments as $condition_aggregate_fragment) {
        if (($conjunction == 'OR') && ($number_of_condition_aggregate_fragments > 1)) {
          $this->mongodbAggregateVersion['$or'][] = $condition_aggregate_fragment;
        }
        elseif (($conjunction == 'AND') && (($number_of_condition_aggregate_fragments > 1) || !empty($embedded_tables_condition_fragments))) {
          $this->mongodbAggregateVersion['$and'][] = $condition_aggregate_fragment;
        }
        else {
          $this->mongodbAggregateVersion = $condition_aggregate_fragment;
        }
      }

      if ($this->useElementMatch) {
        // Combine multiple conditions on the same embedded table in a MongoDB
        // "$elemMatch". See:
        // https://docs.mongodb.com/manual/tutorial/query-array-of-documents/.
        $number_of_embedded_tables_condition_fragments = count($embedded_tables_condition_fragments);
        foreach ($embedded_tables_condition_fragments as $embedded_table => $condition_fragments) {
          if (($number_of_embedded_tables_condition_fragments > 1) || ($number_of_condition_fragments > 0)) {
            if (in_array($embedded_table, [$this->mongodbBaseTable, $this->mongodbBaseAlias])) {
              $this->mongodbVersion['$and'] = [$condition_fragments];
              $this->mongodbAggregateVersion['$and'] = [$condition_fragments];
            }
            else {
              $this->mongodbVersion['$and'][] = [$embedded_table => ['$elemMatch' => $condition_fragments]];
              $this->mongodbAggregateVersion['$and'][] = [$embedded_table => ['$elemMatch' => $condition_fragments]];
              $this->mongodbElemMatchEmbeddedTables[] = $embedded_table;
            }
          }
          else {
            if (in_array($embedded_table, [$this->mongodbBaseTable, $this->mongodbBaseAlias])) {
              $this->mongodbVersion['$and'] = [$condition_fragments];
              $this->mongodbAggregateVersion['$and'] = [$condition_fragments];
            }
            else {
              $this->mongodbVersion = $this->mongodbAggregateVersion = [$embedded_table => ['$elemMatch' => $condition_fragments]];
              $this->mongodbElemMatchEmbeddedTables[] = $embedded_table;
            }
          }
        }
      }

      if ($expr_condition_fragments) {
        $number_of_expr_condition_fragments = count($expr_condition_fragments);
        if (($conjunction == 'OR') && ($number_of_expr_condition_fragments > 1)) {
          $this->mongodbVersion['$expr']['$or'] = $expr_condition_fragments;
        }
        elseif (($conjunction == 'AND') && ($number_of_expr_condition_fragments > 1)) {
          $this->mongodbVersion['$expr']['$and'] = $expr_condition_fragments;
        }
        else {
          $this->mongodbVersion['$expr'] = reset($expr_condition_fragments);
        }
      }

      if ($expr_condition_aggregate_fragments) {
        $number_of_expr_condition_aggregate_fragments = count($expr_condition_aggregate_fragments);
        if (($conjunction == 'OR') && ($number_of_expr_condition_aggregate_fragments > 1)) {
          $this->mongodbAggregateVersion['$expr']['$or'] = $expr_condition_aggregate_fragments;
        }
        elseif (($conjunction == 'AND') && ($number_of_expr_condition_aggregate_fragments > 1)) {
          $this->mongodbAggregateVersion['$expr']['$and'] = $expr_condition_aggregate_fragments;
        }
        else {
          $this->mongodbAggregateVersion['$expr'] = reset($expr_condition_aggregate_fragments);
        }
      }

      $this->arguments = $arguments;
    }
  }

  /**
   * Get the condition in the MongoDB array version.
   *
   * @return array
   *   A MongoDB array version of the conditions.
   */
  public function toMongoArray() {
    // If the caller forgot to call compile() first, refuse to run.
    if ($this->changed) {
      return [];
    }
    return $this->mongodbVersion;
  }

  /**
   * Get the condition in the MongoDB array condition for an aggregate query.
   *
   * @return array
   *   A MongoDB array version of the conditions.
   */
  public function toMongoAggregateArray() {
    // If the caller forgot to call compile() first, refuse to run.
    if ($this->changed) {
      return [];
    }
    return $this->mongodbAggregateVersion;
  }

  /**
   * Get the condition in the MongoDB array condition for a non-aggregate query.
   *
   * @return array
   *   A MongoDB array version of the conditions.
   */
  public function getEmbeddedTables() {
    return $this->mongodbEmbeddedTables;
  }

  /**
   * Get the embedded tables use in an $elemMatch condition.
   *
   * @return array
   *   The element matched embedded tables.
   */
  public function getElemMatchEmbeddedTables() {
    return $this->mongodbElemMatchEmbeddedTables;
  }

  /**
   * Set the embedded tables to be unwound.
   *
   * @param array $tables
   *   An array of the embedded table names to be unwound for this condition.
   */
  public function setUnwoundTables(array $tables = []) {
    $this->mongodbUnwoundTables = $tables;
    // The value of changed must be set to TRUE. Calling the method compile()
    // will then recompile the condition.
    $this->changed = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function conditionGroupFactory($conjunction = 'AND') {
    return new static($conjunction);
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
