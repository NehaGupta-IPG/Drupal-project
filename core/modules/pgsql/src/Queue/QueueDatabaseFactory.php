<?php

namespace Drupal\pgsql\Queue;

use Drupal\Core\Queue\QueueDatabaseFactory as CoreQueueDatabaseFactory;

/**
 * Queue factory returning the PostgreSQL specific database queue.
 */
class QueueDatabaseFactory extends CoreQueueDatabaseFactory {

  /**
   * {@inheritdoc}
   */
  public function get($name) {
    // This factory is only used when the default connection is PostgreSQL, but
    // the connection the queue runs on is a service argument a site can point
    // at another connection. Fall back to the generic queue when it does, so
    // that PostgreSQL specific SQL is never sent to another database.
    // @see \Drupal\pgsql\PgsqlServiceProvider::alter()
    if ($this->connection->databaseType() === 'pgsql') {
      return new DatabaseQueue($name, $this->connection);
    }
    return parent::get($name);
  }

}
