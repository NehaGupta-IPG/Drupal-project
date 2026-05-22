<?php

namespace Drupal\mongodb\EntityQuery;

use Drupal\Core\Entity\Query\QueryAggregateInterface;

/**
 * The MongoDB implementation of \Drupal\Core\Entity\Query\Sql\QueryAggregate.
 */
class QueryAggregate extends Query implements QueryAggregateInterface {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    return $this
      ->prepare()
      ->addAggregate()
      ->compile()
      ->compileAggregate()
      ->addGroupBy()
      ->addSort()
      ->addSortAggregate()
      ->finish()
      ->result();
  }

  /**
   * {@inheritdoc}
   */
  public function conditionAggregateGroupFactory($conjunction = 'AND') {
    $class = static::getClass($this->namespaces, 'ConditionAggregate');
    return new $class($conjunction, $this, $this->namespaces);
  }

  /**
   * {@inheritdoc}
   */
  public function existsAggregate($field, $function, $langcode = NULL) {
    return $this->conditionAggregate->exists($field, $function, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function notExistsAggregate($field, $function, $langcode = NULL) {
    return $this->conditionAggregate->notExists($field, $function, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function prepare() {
    parent::prepare();
    // Throw away the id fields.
    $this->mongodbFields = [];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function addAggregate() {
    if ($this->aggregate) {
      foreach ($this->aggregate as $aggregate) {
        $mongodb_field = $this->getMongodbFieldName($aggregate['field']);
        $this->mongodbSelect->addGroupByOperation($aggregate['alias'], $mongodb_field, strtoupper($aggregate['function']));
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function compileAggregate() {
    $this->conditionAggregate->compile($this->mongodbSelect);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function addGroupBy() {
    foreach ($this->groupBy as &$group_by) {
      $field = $group_by['field'];
      $mongodb_field = $this->getMongodbFieldName($field);
      $this->mongodbGroupBy[$field] = $mongodb_field;
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function addSortAggregate() {
    if (!$this->count) {
      foreach ($this->sortAggregate as $alias => $sort) {
        $this->mongodbSelect->orderBy($alias, $sort['direction']);
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function result() {
    if ($this->count) {
      return parent::result();
    }

    $rows = $this->mongodbSelect->execute()->fetchAll();
    $rows = $this->updateGroupByResult($rows);
    $rows = $this->sortQueryAggregateResult($rows);

    // Change the rows to an array.
    foreach ($rows as &$row) {
      $row = (array) $row;
    }

    return $rows;
  }

  /**
   * Helper method for updating the group by result of the aggregate query.
   *
   * Two group by results from MongoDB are changed to what Drupal expects:
   *  - The MongoDB field names are changed to the expected drupal field names.
   *  - MongoDB field values are sometimes returned as an array with one value.
   *    Drupal expects the direct value (not in an array).
   *
   * @param array $rows
   *   The rows from the MongoDB aggregate query.
   *
   * @return array
   *   The updated rows.
   */
  protected function updateGroupByResult(array $rows) {
    foreach ($rows as &$row) {
      foreach ($this->groupBy as $group_by) {
        $field = $group_by['field'];
        $mongodb_field = $this->getMongodbFieldName($field);
        if (($field != $mongodb_field) && isset($row->{$mongodb_field})) {
          // Drupal expects group by results keyed by their original name not
          // by their full embedded name.
          $row->{$field} = $row->{$mongodb_field};
          unset($row->{$mongodb_field});
        }

        $aliased_field = str_replace('.', '_', $field);
        if (($field != $aliased_field) && isset($row->{$aliased_field})) {
          // Drupal expects group by results keyed by their original name not
          // by their full embedded name.
          $row->{$field} = $row->{$aliased_field};
          unset($row->{$aliased_field});
        }

        if (!isset($row->{$field})) {
          // MongoDB does not allow aliases to have dots in them. The select
          // query changes them to underscore characters.
          $field_without_dots = str_replace('.', '_', $field);
          if (isset($row->{$field_without_dots})) {
            $field = $field_without_dots;
          }
        }

        if (isset($row->{$field}) && is_array($row->{$field}) && count($row->{$field}) == 1) {
          // Embedded table row values are return as an array.
          $row->{$field} = reset($row->{$field});
        }
      }
    }
    return $rows;
  }

  /**
   * Helper method for sorting the result of the MongoDB aggregate query.
   *
   * @param array $rows
   *   The rows from the MongoDB aggregate query.
   *
   * @return array
   *   The updated rows.
   */
  protected function sortQueryAggregateResult(array $rows) {
    // This array holds all the parameters that will be used by the function
    // array_multisort.
    $multisort_parameters = [];

    // The $this->sort sorting is added first the MongoDB query.
    foreach ($this->sort as $sort) {
      $sort_values = [];
      foreach ($rows as $row) {
        if (isset($row->{$sort['field']})) {
          $sort_values[] = $row->{$sort['field']};
        }
        else {
          $sort_values[] = NULL;
        }
      }

      $multisort_parameters[] = $sort_values;
      $multisort_parameters[] = strtoupper($sort['direction']) == 'DESC' ? SORT_DESC : SORT_ASC;
    }

    // The $this->sortAggregate sorting is added last the MongoDB query.
    foreach ($this->sortAggregate as $alias => $sortAggregate) {
      $sort_values = [];
      foreach ($rows as $row) {
        if (isset($row->{$alias})) {
          $sort_values[] = $row->{$alias};
        }
        else {
          $sort_values[] = NULL;
        }
      }

      $multisort_parameters[] = $sort_values;
      $multisort_parameters[] = strtoupper($sortAggregate['direction']) == 'DESC' ? SORT_DESC : SORT_ASC;
    }

    // Add the array $rows as last to the multisort parameters list. It will
    // then be sorted the same way as the preceding array's.
    $multisort_parameters[] = &$rows;

    // We must call the function array_multisort, because we cannot know with
    // how many sorting variable we are working.
    call_user_func_array('array_multisort', $multisort_parameters);

    return $rows;
  }

}
