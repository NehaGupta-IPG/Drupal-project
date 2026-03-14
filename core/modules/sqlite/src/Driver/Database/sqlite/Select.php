<?php

namespace Drupal\sqlite\Driver\Database\sqlite;

use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Database\Query\Select as QuerySelect;

/**
 * SQLite implementation of \Drupal\Core\Database\Query\Select.
 */
class Select extends QuerySelect {

  /**
   * {@inheritdoc}
   */
  public function forUpdate($set = TRUE): ConditionInterface {
    // SQLite does not support FOR UPDATE so nothing to do.
    return $this;
  }

}
