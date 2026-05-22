<?php

namespace Drupal\mongodb\Plugin\views\query;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\mongodb\Driver\Database\mongodb\MongodbSQLException;
use Drupal\views\Plugin\views\filter\LatestRevision;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

// cspell:ignore datedate datestring substringed

/**
 * The MongoDB implementation of the views query plugin Sql.
 */
class ViewsQuery extends Sql {

  /**
   * The array of conditions.
   *
   * An array of sections of the condition part of the query. Works the same
   * variable $where, with the exception that it can also contain full Condition
   * objects.
   */
  public array $condition = [];

  /**
   * The array of having conditions.
   *
   * An array of sections of the having condition part of the query. Works the
   * same variable $having, with the exception that it can also contain full
   * Condition objects.
   */
  public array $havingCondition = [];

  /**
   * A list of embedded table paths to unwind before the $match.
   *
   * @var array
   */
  protected array $mongodbFilterUnwindPaths = [];

  /**
   * The array containing the fields to added for conditions to the query.
   *
   * @var array
   */
  protected array $mongodbConditionFields = [];

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
   * The array containing the group by operation for the query.
   *
   * @var array
   */
  protected array $mongodbGroupByOperation = [];

  /**
   * The array containing the unrelated joins for the query.
   *
   * @var array
   */
  protected array $mongodbUnrelatedJoins = [];

  /**
   * Get the latest translation affected revision from the query.
   *
   * @var array|bool
   */
  protected array|bool $mongodbLatestTranslationAffectedRevision = [];

  /**
   * Set the latest translation affected revision value for the query.
   *
   * @param bool $value
   *   The value to set. Defaults to true.
   */
  public function setLatestTranslationAffectedRevision(bool $value = TRUE) {
    $this->mongodbLatestTranslationAffectedRevision = (bool) $value;

    return $this->mongodbLatestTranslationAffectedRevision;
  }

  /**
   * Get the current revision table from the view.
   *
   * @return string
   *   The current revision table.
   */
  public function getCurrentRevisionTable() {
    return $this->view->storage->get('current_revision_table');
  }

  /**
   * Get the all revisions table from the view.
   *
   * @return string
   *   The all revisions table.
   */
  public function getAllRevisionsTable() {
    return $this->view->storage->get('all_revisions_table');
  }

  /**
   * Get the latest revision table from the view.
   *
   * @return string
   *   The latest revision table.
   */
  public function getLatestRevisionTable() {
    return $this->view->storage->get('latest_revision_table');
  }

  /**
   * Get the current revision table from the view.
   *
   * @return string
   *   The current revision table.
   */
  public function getTranslationsTable() {
    return $this->view->storage->get('translations_table');
  }

  /**
   * {@inheritdoc}
   */
  public function addField($table, $field, $alias = '', $params = []) {
    // We check for this specifically because it gets a special alias.
    if ($table == $this->view->storage->get('base_table') && $field == $this->view->storage->get('base_field') && empty($alias)) {
      $alias = $this->view->storage->get('base_field');
    }

    if ($table && empty($this->tableQueue[$table])) {
      $this->ensureTable($table);
    }

    if (!$alias && $table) {
      $alias = $table . '_' . str_replace('.', '_', $field);
    }

    // Make sure an alias is assigned.
    $alias = $alias ?: $field;

    $alias = $this->truncateAlias($alias);

    // Create a field info array.
    $field_info = [
      'field' => $field,
      'table' => $table,
      'alias' => $alias,
    ] + $params;

    // Test to see if the field is actually the same or not. Due to
    // differing parameters changing the aggregation function, we need
    // to do some automatic alias collision detection:
    $base = $alias;
    $counter = 0;
    while (!empty($this->fields[$alias]) && $this->fields[$alias] != $field_info) {
      $field_info['alias'] = $alias = $base . '_' . ++$counter;
    }

    if (empty($this->fields[$alias])) {
      $this->fields[$alias] = $field_info;
    }

    $field = $this->removeEmbeddedTableDataForNonRelationalDatabases($field);

    // Keep track of all aliases used.
    $this->fieldAliases[$table][$field] = $alias;

    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  protected function truncateAlias($alias) {
    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  protected function removeEmbeddedTableDataForNonRelationalDatabases($field) {
    $last_dot_position = strrpos($field, '.');
    if ($last_dot_position !== FALSE) {
      $field = substr($field, ($last_dot_position + 1));
    }
    return $field;
  }

  /**
   * {@inheritdoc}
   */
  public function addWhere($group, $field, $value = NULL, $operator = NULL) {
    throw new MongodbSQLException('MongoDB does not support SQL strings. Use the method ViewsQuery::addCondition() instead of this method.');
  }

  /**
   * {@inheritdoc}
   */
  public function addWhereExpression($group, $snippet, $args = []) {
    throw new MongodbSQLException('MongoDB does not support SQL strings. Use the method ViewsQuery::addCondition() instead of this method.');
  }

  /**
   * Path to be unwound before the condition.
   *
   * @param string $path
   *   The path of the embedded table to be unwound.
   */
  public function addFilterUnwindPath($path) {
    $this->mongodbFilterUnwindPaths[] = $path;
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
    $this->mongodbConditionFields[$alias] = $field;
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
    $this->mongodbConcatFields[$alias] = [
      'fields' => $fields,
      'alias' => $alias,
      'integer fields' => $integer_fields,
    ];
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
    $this->mongodbFieldsLength[$alias] = $field;
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
    $last_dot = strrpos($field, '.');
    if ($last_dot !== FALSE) {
      $this->addFilterUnwindPath(substr($field, 0, $last_dot));
    }

    $this->mongodbDateDateFormattedFields[$alias] = [
      'field' => $field,
      'alias' => $alias,
      'format' => $format,
    ];
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
    $last_dot = strrpos($field, '.');
    if ($last_dot !== FALSE) {
      $this->addFilterUnwindPath(substr($field, 0, $last_dot));
    }

    $this->mongodbDateStringFormattedFields[$alias] = [
      'field' => $field,
      'alias' => $alias,
      'format' => $format,
    ];
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
    $this->mongodbSubstringFields[$alias] = [
      'alias' => $alias,
      'field' => $field,
      'start' => $start,
      'length' => $length,
    ];
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
    $this->mongodbSumMultiplyFields[$alias] = [
      'alias' => $alias,
      'fields' => $fields,
      'values' => $values,
    ];
  }

  /**
   * Add a condition clause to the query.
   *
   * @param string $group
   *   The WHERE group to add these to; groups are used to create AND/OR
   *   sections. Groups cannot be nested. Use 0 as the default group.
   *   If the group does not yet exist it will be created as an AND group.
   * @param string $field
   *   The name of the field to check.
   * @param mixed $value
   *   The value to test the field against. In most cases, this is a scalar. For
   *   more complex options, it is an array. The meaning of each element in the
   *   array is dependent on the $operator.
   * @param string $operator
   *   The comparison operator, such as =, <, or >=. It also accepts more
   *   complex options such as IN, LIKE, LIKE BINARY, or BETWEEN. Defaults to =.
   *
   * @see \Drupal\views\Plugin\views\query\Sql::addWhere()
   */
  public function addCondition($group, $field, $value = NULL, $operator = '=') {
    // Ensure all variants of 0 are actually 0. Thus '', 0 and NULL are all
    // the default group.
    if (empty($group)) {
      $group = 0;
    }

    // Check for a group.
    if (!isset($this->condition[$group])) {
      $this->setWhereGroup('AND', $group, 'condition');
    }

    if ($field instanceof ConditionInterface) {
      $this->condition[$group]['conditions'][] = $field;
    }
    else {
      $substitutions = \Drupal::moduleHandler()->invokeAll('views_query_substitutions', [$this->view]);
      if (is_array($value)) {
        foreach ($value as &$val) {
          foreach ($substitutions as $substitution_key => $substitution_value) {
            if ($substitution_key === $val) {
              $val = $substitution_value;
            }
          }
        }
      }
      else {
        foreach ($substitutions as $substitution_key => $substitution_value) {
          if ($substitution_key === $value) {
            $value = $substitution_value;
          }
        }
      }

      $this->condition[$group]['conditions'][] = [
        'field' => $field,
        'value' => $value,
        'operator' => $operator,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addHavingExpression($group, $snippet, $args = []) {
    throw new MongodbSQLException('MongoDB does not support SQL strings. Use the method ViewsQuery::addHavingCondition() instead of this method.');
  }

  /**
   * Add a HAVING condition clause to the query.
   *
   * @param string $group
   *   The HAVING group to add these to; groups are used to create AND/OR
   *   sections. Groups cannot be nested. Use 0 as the default group.
   *   If the group does not yet exist it will be created as an AND group.
   * @param string $field
   *   The name of the field to check.
   * @param mixed $value
   *   The value to test the field against. In most cases, this is a scalar. For
   *   more complex options, it is an array. The meaning of each element in the
   *   array is dependent on the $operator.
   * @param string $operator
   *   The comparison operator, such as =, <, or >=. It also accepts more
   *   complex options such as IN, LIKE, LIKE BINARY, or BETWEEN. Defaults to =.
   *   If $field is a string you have to use 'formula' here.
   *
   * @see \Drupal\Core\Database\Query\ConditionInterface::condition()
   * @see \Drupal\Core\Database\Query\Condition
   */
  public function addHavingCondition($group, $field, $value = NULL, $operator = '=') {
    // Ensure all variants of 0 are actually 0. Thus '', 0 and NULL are all
    // the default group.
    if (empty($group)) {
      $group = 0;
    }

    // Check for a group.
    if (!isset($this->havingCondition[$group])) {
      $this->setWhereGroup('AND', $group, 'havingCondition');
    }

    if ($field instanceof ConditionInterface) {
      $this->havingCondition[$group]['conditions'][] = $field;
    }
    else {
      $this->havingCondition[$group]['conditions'][] = [
        'field' => $field,
        'value' => $value,
        'operator' => $operator,
      ];
    }
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
      $this->mongodbGroupByOperation[$alias] = [
        'field' => $field,
        'alias' => $alias,
        'operator' => $operator,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addOrderBy($table, $field = NULL, $order = 'ASC', $alias = '', $params = []) {
    // Only ensure the table if it's not the special random key.
    // @todo Maybe it would make sense to just add an addOrderByRand or
    // something similar.
    if ($table && $table != 'rand') {
      $this->ensureTable($table);
    }

    $field = $this->removeCurrentRevisionTableForNonRelationalDatabases($field);

    // Only fill out this aliasing if there is a table;
    // otherwise we assume it is a formula.
    if (!$alias && $table) {
      $as = $table . '_' . str_replace('.', '_', ($field ?? ''));
    }
    else {
      $as = $alias;
    }

    if ($field) {
      $as = $this->addField($table, $field, $as, $params);
    }

    $this->orderby[] = [
      'field' => $as,
      'direction' => strtoupper($order),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function removeCurrentRevisionTableForNonRelationalDatabases($field) {
    if (!empty($this->getCurrentRevisionTable()) && is_string($field)) {
      $search_needle = $this->getCurrentRevisionTable() . '.';
      $field = str_replace($search_needle, '', $field);
    }
    return $field;
  }

  /**
   * Generates a unique placeholder used in the db query.
   */
  public function placeholder($base = 'views') {
    static $placeholders = [];
    if (!isset($placeholders[$base])) {
      $placeholders[$base] = 0;
      return $base;
    }
    else {
      return $base . ++$placeholders[$base];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function buildCondition($where = 'where') {
    foreach ($this->$where as $group => &$info) {
      if (!empty($info['conditions'])) {
        foreach ($info['conditions'] as &$clause) {
          if (is_array($clause) && isset($clause['operator']) && !in_array($clause['operator'], ['DATEDATE', 'DATESTRING'], TRUE) && isset($clause['value'])) {
            if (strtoupper(substr($clause['operator'], -7)) == '_STRING') {
              if (is_array($clause['value'])) {
                foreach ($clause['value'] as &$clause_value) {
                  $clause_value = (string) $clause_value;
                }
              }
              else {
                $clause['value'] = (string) $clause['value'];
              }
            }
            elseif (!is_array($clause['value']) && (is_int($clause['value']) || (is_string($clause['value']) && ctype_digit($clause['value'])))) {
              $clause['value'] = (int) $clause['value'];
            }
            elseif (is_array($clause['value'])) {
              foreach ($clause['value'] as &$clause_value) {
                if (is_int($clause_value) || ctype_digit($clause_value)) {
                  $clause_value = (int) $clause_value;
                }
              }
            }
          }
        }
      }
    }

    $has_condition = FALSE;
    $has_arguments = FALSE;
    $has_filter = FALSE;

    /** @var \Drupal\Core\Database\Connection $connection */
    $connection = $this->getConnection();

    $main_group = $connection->condition('AND');
    $filter_group = $this->groupOperator == 'OR' ? $connection->condition('OR') : $connection->condition('AND');

    foreach ($this->$where as $group => &$info) {
      if (!empty($info['conditions'])) {
        $sub_group = $info['type'] == 'OR' ? $connection->condition('OR') : $connection->condition('AND');
        foreach ($info['conditions'] as &$clause) {
          if ($clause instanceof ConditionInterface) {
            $has_condition = TRUE;
            $sub_group->condition($clause);
          }
          elseif (is_array($clause) && ($clause['operator'] == 'formula')) {
            $has_condition = TRUE;
            $sub_group->where($clause['field'], $clause['value']);
          }
          elseif (is_array($clause)) {
            $has_condition = TRUE;
            // Operators that end with the sting "_STRING" should have that
            // part removed.
            $clause_operator = strtoupper(substr($clause['operator'], -7)) == '_STRING' ? 'IN' : $clause['operator'];
            $sub_group->condition($clause['field'], $clause['value'], $clause_operator);
          }
          else {
            $has_condition = FALSE;
          }
        }

        // Add the item to the filter group.
        if ($group != 0) {
          $has_filter = TRUE;
          $filter_group->condition($sub_group);
        }
        else {
          $has_arguments = TRUE;
          $main_group->condition($sub_group);
        }
      }
    }

    if ($has_filter) {
      $main_group->condition($filter_group);
    }

    if (!$has_arguments && $has_condition) {
      return $filter_group;
    }
    if ($has_arguments && $has_condition) {
      return $main_group;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasLatestRevisionFilter() {
    foreach ($this->view->filter as $handler) {
      if ($handler instanceof LatestRevision) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function compileFields($query) {
    foreach ($this->fields as $field) {
      $fieldname = '';
      if (!empty($field['table'])) {
        $fieldname .= $field['table'] . '.';
      }
      $fieldname .= $field['field'];
      $field_alias = (!empty($field['alias']) ? $field['alias'] : $fieldname);

      if (!empty($field['count'])) {
        // Retained for compatibility.
        $field['function'] = 'count';
      }

      if (!empty($field['function'])) {
        $this->compileFieldsNotEmptyFieldFunction($query, $field, $fieldname, $field_alias);
      }
      // This is a formula, using no tables.
      elseif (empty($field['table'])) {
        $this->compileFieldsEmptyFieldTable($query, $field, $fieldname, $field_alias);
      }
      elseif ($this->distinct && !in_array($fieldname, $this->groupby)) {
        $query->addField(!empty($field['table']) ? $field['table'] : $this->view->storage->get('base_table'), $field['field'], $field_alias);
      }
      elseif (empty($field['aggregate'])) {
        $query->addField(!empty($field['table']) ? $field['table'] : $this->view->storage->get('base_table'), $field['field'], $field_alias);
      }

      if ($this->getCountOptimized) {
        // We only want the first field in this case.
        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function compileFieldsNotEmptyFieldFunction($query, $field, $fieldname, $field_alias) {
    $query->addGroupByOperation($field_alias, $fieldname, strtoupper($field['function']));
    $this->hasAggregate = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function compileFieldsEmptyFieldTable($query, $field, $fieldname, $field_alias) {
    $query->addGroupField($field_alias, $fieldname);
  }

  /**
   * {@inheritdoc}
   */
  public function query($get_count = FALSE) {
    // Check query distinct value.
    if (empty($this->noDistinct) && $this->distinct && !empty($this->fields)) {
      $base_field_alias = $this->addField($this->view->storage->get('base_table'), $this->view->storage->get('base_field'));
      $this->addGroupBy($base_field_alias);
      $distinct = TRUE;
    }

    // An optimized count query includes just the base field instead of all the
    // fields. Determine of this query qualifies by checking for a groupby or
    // distinct.
    if ($get_count && !$this->groupby) {
      foreach ($this->fields as $field) {
        if (!empty($field['distinct']) || !empty($field['function'])) {
          $this->getCountOptimized = FALSE;
          break;
        }
      }
    }
    else {
      $this->getCountOptimized = FALSE;
    }
    if (!isset($this->getCountOptimized)) {
      $this->getCountOptimized = TRUE;
    }

    // Go ahead and build the query.
    $query = $this->getConnection()
      ->select($this->view->storage->get('base_table'), $this->view->storage->get('base_table'))
      ->addTag('views')
      ->addTag('views_' . $this->view->storage->id());

    // Add the tags added to the view itself.
    foreach ($this->tags as $tag) {
      $query->addTag($tag);
    }

    if (!empty($distinct)) {
      $query->distinct();
    }

    $this->addFieldsForNonRelationalDatabases($query);

    $this->addJoins($query);

    // Assemble the groupby clause, if any.
    $this->hasAggregate = FALSE;
    $non_aggregates = $this->getNonAggregates();
    if ($this->isAggregateQuery()) {
      $this->hasAggregate = TRUE;
    }
    elseif (!$this->hasAggregate) {
      // Allow 'GROUP BY' even no aggregation function has been set.
      $this->hasAggregate = $this->view->display_handler->getOption('group_by');
    }
    $groupby = [];
    if ($this->hasAggregate && (!empty($this->groupby) || !empty($non_aggregates))) {
      $groupby = array_unique(array_merge($this->groupby, $non_aggregates));
    }

    // Make sure each entity table has the base field added so that the
    // entities can be loaded.
    $entity_information = $this->getEntityTableInfo();
    if ($entity_information) {
      $params = [];
      if ($groupby) {
        // Handle grouping, by retrieving the minimum entity_id.
        $params = [
          'function' => 'min',
        ];
      }

      foreach ($entity_information as $info) {
        $entity_type = \Drupal::entityTypeManager()->getDefinition($info['entity_type']);
        $base_field = !$info['revision'] ? $entity_type->getKey('id') : $entity_type->getKey('revision');
        $this->addField($info['alias'], $base_field, '', $params);
      }
    }

    // Add all fields to the query.
    $this->compileFields($query);

    // Add groupby.
    if ($groupby) {
      foreach ($groupby as $field) {
        $field = $this->updateFieldForGroupByQuery($field);
        $query->groupBy($field);
      }
      $this->addGroupByQueryCondition($query);
    }

    if (!$this->getCountOptimized) {
      // We only add the orderby if we're not counting.
      if ($this->orderby) {
        foreach ($this->orderby as $order) {
          if ($order['field'] == 'rand_') {
            $query->orderRandom();
          }
          else {
            $query->orderBy($order['field'], $order['direction']);
          }
        }
      }
    }

    $this->addQueryCondition($query);

    // Add a query comment.
    if (!empty($this->options['query_comment'])) {
      $query->comment($this->options['query_comment']);
    }

    // Add the query tags.
    if (!empty($this->options['query_tags'])) {
      foreach ($this->options['query_tags'] as $tag) {
        $query->addTag($tag);
      }
    }

    // Add all query substitutions as metadata.
    $substitutions = \Drupal::moduleHandler()->invokeAll('views_query_substitutions', [$this->view]);
    if (isset($substitutions['***CURRENT_USER***'])) {
      // User IDS are stored as integers in MongoDB and MongoDB does not except
      // string versions of integer values.
      $substitutions['***CURRENT_USER***'] = (int) $substitutions['***CURRENT_USER***'];
    }
    $query->addMetaData('views_substitutions', $substitutions);

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  protected function addFieldsForNonRelationalDatabases(&$query) {
    // Do this if the base table is part of a revisionable entity.
    if ($this->view->storage->get('mongodb_base_table') != $this->view->storage->get('original_base_table')) {
      if (!empty($this->getAllRevisionsTable()) && !empty($this->getLatestRevisionTable())) {
        if ($this->hasLatestRevisionFilter()) {
          $query->embeddedTableToUseAsBaseTable($this->getLatestRevisionTable());
        }
        else {
          $query->embeddedTableToUseAsBaseTable($this->getAllRevisionsTable());
        }
      }
      elseif (!empty($this->getCurrentRevisionTable())) {
        $query->embeddedTableToUseAsBaseTable($this->getCurrentRevisionTable());
      }
      elseif (!empty($this->getTranslationsTable())) {
        $query->embeddedTableToUseAsBaseTable($this->getTranslationsTable());
      }
    }

    foreach ($this->mongodbFilterUnwindPaths as $path) {
      $query->addFilterUnwindPath($path);
    }

    foreach ($this->mongodbConditionFields as $alias => $field) {
      $query->addConditionField($alias, $field);
    }

    foreach ($this->mongodbConcatFields as $alias => $data) {
      $query->addConcatField($alias, $data['fields'], $data['integer fields']);
    }

    foreach ($this->mongodbSubstringFields as $alias => $data) {
      $query->addSubstringField($alias, $data['field'], $data['start'], $data['length']);
    }

    foreach ($this->mongodbFieldsLength as $alias => $field) {
      $query->addFieldLength($alias, $field);
    }

    foreach ($this->mongodbDateDateFormattedFields as $alias => $data) {
      $query->addDateDateFormattedField($alias, $data['field'], $data['format']);
    }

    foreach ($this->mongodbDateStringFormattedFields as $alias => $data) {
      $query->addDateStringFormattedField($alias, $data['field'], $data['format']);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function addJoins(&$query) {
    // Add all the tables to the query via joins. We assume all LEFT joins.
    foreach ($this->tableQueue as $table) {
      if (is_object($table['join'])) {
        $table['join']->buildMongodbJoin($query, $table, $this);
      }
    }

    foreach ($this->mongodbUnrelatedJoins as $data) {
      $query->addMongodbJoin($data['type'], $data['table'], $data['field'], $data['left_table'], $data['left_field'], $data['operator'], $data['alias'], $data['extra']);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function isAggregateQuery() {
    if (count($this->having) || count($this->havingCondition) || count($this->groupby)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function updateBaseFieldForRevisionableForNonRelationalDatabases($base_field, $info) {
    if ($info['revision'] && ($info['relationship_id'] == 'none') && ($this->view->storage->get('mongodb_base_table') != $this->view->storage->get('original_base_table')) && !empty($this->getAllRevisionsTable()) && !empty($this->getLatestRevisionTable())) {
      if ($this->hasLatestRevisionFilter()) {
        $base_field = $this->getLatestRevisionTable() . '.' . $base_field;
      }
      else {
        $base_field = $this->getAllRevisionsTable() . '.' . $base_field;
      }
    }
    return $base_field;
  }

  /**
   * {@inheritdoc}
   */
  protected function updateFieldForGroupByQuery($field) {
    return $field;
  }

  /**
   * {@inheritdoc}
   */
  protected function addGroupByQueryCondition(&$query) {
    if (!empty($this->havingCondition) && $condition = $this->buildCondition('havingCondition')) {
      $query->havingCondition($condition);

      foreach ($this->mongodbGroupByOperation as $operation) {
        $query->addGroupByOperation($operation['alias'], $operation['field'], $operation['operator']);
      }
    }
    foreach ($this->mongodbSumMultiplyFields as $alias => $data) {
      $query->addSumMultiplyExpression($alias, $data['fields'], $data['values']);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function addQueryCondition(&$query) {
    if (!empty($this->condition) && $condition = $this->buildCondition('condition')) {
      $query->condition($condition);

      // Add the unwind actions for the embedded tables for the condition
      // fields.
      foreach ($this->condition as $conditions) {
        if (isset($conditions['conditions']) && is_array($conditions['conditions'])) {
          foreach ($conditions['conditions'] as $condition) {
            if (is_array($condition) && isset($condition['field']) && is_string($condition['field'])) {
              $position = strrpos($condition['field'], '.');
              if ($position !== FALSE) {
                $query->addFilterUnwindPath(substr($condition['field'], 0, $position));
              }
            }
          }
        }
      }
    }

    if (!empty($this->mongodbFilterUnwindPaths)) {
      foreach ($this->mongodbFilterUnwindPaths as $unwind_path) {
        $query->addFilterUnwindPath($unwind_path);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ViewExecutable $view) {
    $query = $view->build_info['query'];
    $count_query = $view->build_info['count_query'];

    $query->addMetaData('view', $view);
    $count_query->addMetaData('view', $view);

    if (empty($this->options['disable_sql_rewrite'])) {
      $base_table_data = Views::viewsData()->get($this->view->storage->get('base_table'));
      if (isset($base_table_data['table']['base']['access query tag'])) {
        $access_tag = $base_table_data['table']['base']['access query tag'];
        $query->addTag($access_tag);
        $count_query->addTag($access_tag);
      }

      if (isset($base_table_data['table']['base']['query metadata'])) {
        foreach ($base_table_data['table']['base']['query metadata'] as $key => $value) {
          $query->addMetaData($key, $value);
          $count_query->addMetaData($key, $value);
        }
      }
    }

    if ($query) {
      $additional_arguments = \Drupal::moduleHandler()->invokeAll('views_query_substitutions', [$view]);

      // Count queries must be run through the preExecute() method.
      // If not, then hook_query_node_access_alter() may munge the count by
      // adding a distinct against an empty query string
      // (e.g. COUNT DISTINCT(1) ...) and no pager will return.
      // See \Drupal\Core\Database\Query\PagerSelectExtender::execute()
      // See https://www.drupal.org/node/1046170.
      $count_query->preExecute();

      // Build the count query.
      $count_query = $count_query->countQuery();

      // Add additional arguments as a fake condition.
      // XXX: this doesn't work, because PDO mandates that all bound arguments
      // are used on the query. TODO: Find a better way to do this.
      if (!empty($additional_arguments)) {
        // $query->where('1 = 1', $additional_arguments);
        // $count_query->where('1 = 1', $additional_arguments);
      }

      $start = microtime(TRUE);

      try {
        if ($view->pager->useCountQuery() || !empty($view->get_total_rows)) {
          $view->pager->executeCountQuery($count_query);
        }

        // Let the pager modify the query to add limits.
        $view->pager->preExecute($query);

        if (!empty($this->limit) || !empty($this->offset)) {
          // We can't have an offset without a limit, so provide a very large
          // limit instead.
          $limit = intval(!empty($this->limit) ? $this->limit : 999999);
          $offset = intval(!empty($this->offset) ? $this->offset : 0);
          $query->range($offset, $limit);
        }

        $result = $query->execute();
        $result->setFetchMode(\PDO::FETCH_CLASS, 'Drupal\views\ResultRow');

        // Setup the result row objects.
        $view->result = iterator_to_array($result);

        array_walk($view->result, function (ResultRow $row, $index) {
          $row->index = $index;
        });

        if ($this->mongodbLatestTranslationAffectedRevision) {
          $this->updateLatestTranslationAffectedRevision($view->result);
        }
        $this->updateViewsResultForNonRelationalDatabases($view->result);

        $view->pager->postExecute($view->result);
        $view->pager->updatePageInfo();
        $view->total_rows = $view->pager->getTotalItems();

        // Load all entities contained in the results.
        $this->loadEntities($view->result);
      }
      catch (DatabaseExceptionWrapper $e) {
        $view->result = [];
        if (!empty($view->live_preview)) {
          $this->messenger->addError($e->getMessage());
        }
        else {
          throw new DatabaseExceptionWrapper("Exception in {$view->storage->label()}[{$view->storage->id()}]: {$e->getMessage()}");
        }
      }

    }
    else {
      $start = microtime(TRUE);
    }
    $view->execute_time = microtime(TRUE) - $start;
  }

  /**
   * Remove results that do not have the latest translation affected revision.
   */
  protected function updateLatestTranslationAffectedRevision(&$results) {
    $entity_information = $this->getEntityTableInfo();
    // No entity tables found, nothing else to do here.
    if (empty($entity_information)) {
      return;
    }

    // Extract all entity types from entity_information.
    $entity_types = [];
    foreach ($entity_information as $info) {
      $entity_type = $info['entity_type'];
      if (!isset($entity_types[$entity_type])) {
        $entity_types[$entity_type] = $this->entityTypeManager->getDefinition($entity_type);
      }
    }

    $latest_translations_affected_revisions = [];
    foreach ($entity_information as $info) {
      $entity_type = $info['entity_type'];
      /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_info */
      $entity_info = $entity_types[$entity_type];

      $id_key = $entity_info->getKey('revision');
      $id_alias = $this->getFieldAlias($info['alias'], $id_key);
      $langcode_key = $entity_info->getKey('langcode');
      $langcode_alias = $this->getFieldAlias($info['alias'], $langcode_key);

      // Get the LATEST translation affected revision for each entity_type and
      // translation.
      foreach ($results as &$result) {
        if (isset($result->{$id_alias}) && ($result->{$id_alias} != '') && isset($result->{$langcode_alias}) && ($result->{$langcode_alias} != '')) {
          if (!isset($latest_translations_affected_revisions[$entity_type][$result->{$langcode_alias}])) {
            $latest_translations_affected_revisions[$entity_type][$result->{$langcode_alias}] = $result->{$id_alias};
          }
          elseif ($latest_translations_affected_revisions[$entity_type][$result->{$langcode_alias}] < $result->{$id_alias}) {
            $latest_translations_affected_revisions[$entity_type][$result->{$langcode_alias}] = $result->{$id_alias};
          }
        }
      }

      // Remove the results rows that do not have the LATEST translation
      // affected revision.
      foreach ($results as $row_id => &$result) {
        if (isset($result->{$id_alias}) && ($result->{$id_alias} != '') && isset($result->{$langcode_alias}) && ($result->{$langcode_alias} != '') &&
          isset($latest_translations_affected_revisions[$entity_type][$result->{$langcode_alias}]) && ($latest_translations_affected_revisions[$entity_type][$result->{$langcode_alias}] > $result->{$id_alias})) {
          unset($results[$row_id]);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function updateViewsResultForNonRelationalDatabases(&$results) {
    foreach ($results as &$result) {
      foreach ($this->fields as $field) {
        // Change the integer values from MongoDB to the expected string values
        // from relational databases.
        if (isset($result->{$field['alias']}) && is_int($result->{$field['alias']})) {
          $result->{$field['alias']} = (string) $result->{$field['alias']};
        }

        // Move the join results from their arrayed form from MongoDB to the
        // base result that is expected from relational databases.
        if (isset($field['table']) && $field['table'] != $this->view->storage->get('base_table') && isset($result->{$field['alias']}) && is_array($result->{$field['alias']})) {
          $result->{$field['alias']} = reset($result->{$field['alias']});
        }

        if (isset($field['table']) && $field['table'] == $this->view->storage->get('base_table') && isset($result->{$field['alias']}) && !isset($result->{$field['field']})) {
          $result->{$field['field']} = $result->{$field['alias']};
        }

        if (isset($field['alias']) && isset($field['table']) && $field['table'] != $this->view->storage->get('base_table')) {
          $joined_table_alias = $field['table'] . '_' . $field['alias'];
          if (!empty($joined_table_alias) && isset($result->{$joined_table_alias}) && is_array($result->{$joined_table_alias})) {
            $result->{$field['alias']} = reset($result->{$joined_table_alias});
            $result->{$joined_table_alias} = $result->{$field['alias']};
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadEntities(&$results) {
    $entity_information = $this->getEntityTableInfo();
    // No entity tables found, nothing else to do here.
    if (empty($entity_information)) {
      return;
    }

    // Extract all entity types from entity_information.
    $entity_types = [];
    foreach ($entity_information as $info) {
      $entity_type = $info['entity_type'];
      if (!isset($entity_types[$entity_type])) {
        $entity_types[$entity_type] = $this->entityTypeManager->getDefinition($entity_type);
      }
    }

    // Assemble a list of entities to load.
    $entity_ids_by_type = [];
    $revision_ids_by_type = [];
    foreach ($entity_information as $info) {
      $relationship_id = $info['relationship_id'];
      $entity_type = $info['entity_type'];
      /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_info */
      $entity_info = $entity_types[$entity_type];
      $revision = $info['revision'];
      $id_key = !$revision ? $entity_info->getKey('id') : $entity_info->getKey('revision');
      $id_alias = $this->getFieldAlias($info['alias'], $id_key);

      foreach ($results as $index => $result) {
        $id_value = NULL;
        if (isset($result->{$id_alias}) && $result->{$id_alias} != '') {
          $id_value = $result->$id_alias;
        }
        else {
          $id_value = $this->getResultIdValueForNonRelationalDatabases($result, $id_alias, $info);
        }

        // Store the entity id if it was found.
        if (!is_null($id_value)) {
          if ($revision) {
            $revision_ids_by_type[$entity_type][$index][$relationship_id] = $id_value;
          }
          else {
            $entity_ids_by_type[$entity_type][$index][$relationship_id] = $id_value;
          }
        }
      }
    }

    // Load all entities and assign them to the correct result row.
    foreach ($entity_ids_by_type as $entity_type => $ids) {
      $entity_storage = $this->entityTypeManager->getStorage($entity_type);
      $flat_ids = iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveArrayIterator($ids)), FALSE);

      $entities = $entity_storage->loadMultiple(array_unique($flat_ids));
      $results = $this->assignEntitiesToResult($ids, $entities, $results);
    }

    // Now load all revisions.
    foreach ($revision_ids_by_type as $entity_type => $revision_ids) {
      $entity_storage = $this->entityTypeManager->getStorage($entity_type);
      $entities = [];

      foreach ($revision_ids as $revision_id_by_relationship) {
        foreach ($revision_id_by_relationship as $revision_id) {
          // Drupal core currently has no way to load multiple revisions.
          $entity = $entity_storage->loadRevision($revision_id);
          $entities[$revision_id] = $entity;
        }
      }

      $results = $this->assignEntitiesToResult($revision_ids, $entities, $results);
    }
  }

  /**
   * Transform result id for non relational databases.
   *
   * Helper method for changing allowing non relational databases to transform
   * their views result to match that of relational databases.
   *
   * @param \Drupal\views\ResultRow $result
   *   The result of the SQL query.
   * @param string $id_alias
   *   The key of the entity id to search for.
   * @param array $info
   *   The array with table information.
   */
  protected function getResultIdValueForNonRelationalDatabases($result, $id_alias, $info) {
    $alias_id_alias = $info['alias'] . '_' . $id_alias;
    if (isset($result->{$alias_id_alias}) && $result->{$alias_id_alias} != '') {
      return $result->{$alias_id_alias};
    }
    return NULL;
  }

}
