<?php

namespace Drupal\Core\Entity\Query\Null;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\Query\QueryAggregateInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\Query\Sql\ConditionAggregate;

/**
 * Defines the entity query for configuration entities.
 */
class Query extends QueryBase implements QueryInterface, QueryAggregateInterface {

  /**
   * {@inheritdoc}
   */
  public function execute(?RefinableCacheableDependencyInterface $cacheableMetadata = NULL) {
    if ($cacheableMetadata === NULL) {
      // @todo Add correct CR number.
      @trigger_error('Calling ' . __METHOD__ . '() without the $cacheableMetadata argument is deprecated in drupal:11.5.0 and is removed from drupal:12.0.0. See https://www.drupal.org/node/1234567', E_USER_DEPRECATED);
    }
    if ($this->count) {
      return 0;
    }
    return [];
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
  public function conditionAggregateGroupFactory($conjunction = 'AND') {
    return new ConditionAggregate($conjunction, $this);
  }

}
