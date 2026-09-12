<?php

declare(strict_types=1);

namespace Drupal\pgsql\Queue;

use Drupal\Core\Queue\QueueDatabaseFactory as CoreQueueDatabaseFactory;

/**
 * Creates the PostgreSQL implementation of the database queue.
 */
class QueueDatabaseFactory extends CoreQueueDatabaseFactory {

  /**
   * {@inheritdoc}
   */
  public function get($name) {
    return new DatabaseQueue($name, $this->connection);
  }

}
