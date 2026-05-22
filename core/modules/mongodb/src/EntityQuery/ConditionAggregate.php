<?php

namespace Drupal\mongodb\EntityQuery;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\Query\ConditionAggregateInterface;
use Drupal\Core\Entity\Query\Sql\ConditionAggregate as CoreConditionAggregate;
use Drupal\mongodb\Driver\Database\mongodb\Condition as MongodbCondition;

/**
 * MongoDB implementation of \Drupal\Core\Entity\Query\Sql\ConditionAggregate.
 */
class ConditionAggregate extends CoreConditionAggregate {

  /**
   * {@inheritdoc}
   */
  public function compile($conditionContainer) {
    // If this is not the top level condition group then the MongoDB query is
    // added to the $conditionContainer object by this function itself. The
    // MongoDB query object is only necessary to pass to Query::addField() so it
    // can join tables as necessary. On the other hand, conditions need to be
    // added to the $conditionContainer object to keep grouping.
    $mongodb_query = $conditionContainer instanceof SelectInterface ? $conditionContainer : $conditionContainer->mongodbQuery;

    foreach ($this->conditions as $condition) {
      if ($condition['field'] instanceof ConditionAggregateInterface) {
        $mongodb_condition = new MongodbCondition($condition['field']->getConjunction());
        // Add the MongoDB query to the object before calling this method again.
        $condition['field']->mongodbQuery = $mongodb_query;
        $condition['field']->compile($mongodb_condition);
        $mongodb_query->condition($mongodb_condition);
      }
      else {
        // We could have solved this by directly calling the method
        // QueryBase::getAggregationAlias(), but the problem is that that method
        // is a protected method.
        $alias = mb_strtolower($condition['field'] . '_' . $condition['function']);

        $conditionContainer->havingCondition($alias, $condition['value'], $condition['operator']);
      }
    }
  }

}
