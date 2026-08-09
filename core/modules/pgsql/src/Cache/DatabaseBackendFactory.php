<?php

declare(strict_types=1);

namespace Drupal\pgsql\Cache;

use Drupal\Core\Cache\DatabaseBackendFactory as CoreDatabaseBackendFactory;

/**
 * Creates the PostgreSQL implementation of the database cache backend.
 */
class DatabaseBackendFactory extends CoreDatabaseBackendFactory {

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    $max_rows = $this->getMaxRowsForBin($bin);
    return new DatabaseBackend($this->connection, $this->checksumProvider, $bin, $this->serializer, $this->time, $max_rows);
  }

}
