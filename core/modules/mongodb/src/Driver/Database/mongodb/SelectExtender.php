<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\Query\SelectExtender as QuerySelectExtender;

/**
 * The MongoDB implementation of \Drupal\Core\Database\Query\SelectExtender.
 */
class SelectExtender extends QuerySelectExtender {

  /**
   * {@inheritdoc}
   */
  public function conditionGroupFactory($conjunction = 'AND') {
    // Make sure that condition is a object of
    // \Drupal\mongodb\Driver\Database\mongodb\Condition.
    return $this->connection->condition($conjunction);
  }

}
