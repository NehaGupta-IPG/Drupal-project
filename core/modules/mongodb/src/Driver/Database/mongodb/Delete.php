<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\Query\Delete as QueryDelete;

/**
 * MongoDB implementation of \Drupal\Core\Database\Query\Delete.
 */
class Delete extends QueryDelete {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    if (count($this->condition)) {
      $this->condition->compile($this->connection, $this);
    }

    try {
      $prefixed_table = $this->connection->getPrefix() . $this->table;
      $result = $this->connection->getConnection()->selectCollection($prefixed_table)->deleteMany(
        $this->condition->toMongoArray(),
        [
          'session' => $this->connection->getMongodbSession(),
        ],
      );

      if ($result && $result->isAcknowledged()) {
        // Return the number of rows that are deleted by query.
        return $result->getDeletedCount();
      }
    }
    catch (\Exception $e) {
      // Rethrow the exception for the calling code.
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function conditionGroupFactory($conjunction = 'AND') {
    // Make sure that condition is a object of
    // \Drupal\mongodb\Driver\Database\mongodb\Condition.
    return $this->connection->condition($conjunction);
  }

}
