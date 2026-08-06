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
    // The pgsql module may be installed while another driver provides the
    // default connection, for example when PostgreSQL is only used for a
    // non-default connection. Only use the PostgreSQL specific queue when
    // the queue connection actually is PostgreSQL.
    if ($this->connection->databaseType() === 'pgsql') {
      return new DatabaseQueue($name, $this->connection);
    }
    return parent::get($name);
  }

}
