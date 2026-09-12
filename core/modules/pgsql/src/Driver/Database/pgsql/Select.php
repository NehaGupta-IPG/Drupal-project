<?php

namespace Drupal\pgsql\Driver\Database\pgsql;

use Drupal\Core\Database\Query\Select as QuerySelect;

/**
 * @addtogroup database
 * @{
 */

/**
 * PostgreSQL implementation of \Drupal\Core\Database\Query\Select.
 */
class Select extends QuerySelect {

  /**
   * {@inheritdoc}
   */
  public function orderRandom() {
    $alias = $this->addExpression('RANDOM()', 'random_field');
    $this->orderBy($alias);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function orderBy($field, $direction = 'ASC') {
    // Only allow ASC and DESC, default to ASC.
    // Emulate MySQL default behavior to sort NULL values first for ascending,
    // and last for descending.
    // @see https://www.postgresql.org/docs/current/queries-order.html
    $direction = strtoupper($direction) == 'DESC' ? 'DESC NULLS LAST' : 'ASC NULLS FIRST';
    $this->order[$field] = $direction;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addExpression($expression, $alias = NULL, $arguments = []) {
    if (empty($alias)) {
      $alias = 'expression';
    }

    // This implements counting in the same manner as the parent method.
    $alias_candidate = $alias;
    $count = 2;
    while (!empty($this->expressions[$alias_candidate])) {
      $alias_candidate = $alias . '_' . $count++;
    }
    $alias = $alias_candidate;

    $this->expressions[$alias] = [
      'expression' => $expression,
      'alias' => $this->connection->escapeAlias($alias_candidate),
      'arguments' => $arguments,
    ];

    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    if ($this->connection->inTransaction()) {
      $savepoint = $this->connection->startTransaction('mimic_implicit_commit');
    }
    try {
      $result = parent::execute();
    }
    catch (\Exception $e) {
      if (isset($savepoint)) {
        $savepoint->rollback();
      }
      throw $e;
    }
    if (isset($savepoint)) {
      $savepoint->commitOrRelease();
    }

    return $result;
  }

}

/**
 * @} End of "addtogroup database".
 */
