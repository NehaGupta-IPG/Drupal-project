<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\Query\Truncate as QueryTruncate;

/**
 * The MongoDB implementation of \Drupal\Core\Database\Query\Truncate.
 */
class Truncate extends QueryTruncate {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $prefixed_table = $this->connection->getPrefix() . $this->table;

    try {
      // DeleteMany with an empty filter and limit set to zero truncates the
      // collection.
      $result = $this->connection->getConnection()->selectCollection($prefixed_table)->deleteMany(
        [],
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
  public function __toString() {
    // Nothing to do.
    return '';
  }

}
