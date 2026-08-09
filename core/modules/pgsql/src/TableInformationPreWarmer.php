<?php

declare(strict_types=1);

namespace Drupal\pgsql;

use Drupal\Core\Database\Connection;
use Drupal\Core\PreWarm\PreWarmableInterface;
use Drupal\pgsql\Driver\Database\pgsql\Connection as PgsqlConnection;
use Drupal\pgsql\Driver\Database\pgsql\TableInformationLoader;

/**
 * Prewarms the table information cache of the default database connection.
 *
 * The blob and sequence information of tables lives in a loader owned by
 * each connection, outside the container. This service only exists to carry
 * the cache_prewarmable tag for the connection the container knows about.
 *
 * @see \Drupal\pgsql\Driver\Database\pgsql\Connection::tableInformation()
 */
class TableInformationPreWarmer implements PreWarmableInterface {

  public function __construct(
    protected readonly Connection $connection,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function preWarm(): void {
    // The default database connection does not have to use this driver.
    if ($this->connection instanceof PgsqlConnection) {
      // Use a fresh loader instead of the connection's own: the connection's
      // loader may already hold the information in memory and would then not
      // persist it, but prewarming must populate the persistent cache for
      // future requests. When the cache is already warm, the fresh loader
      // only performs a single cache get.
      (new TableInformationLoader($this->connection))->loadAll();
    }
  }

}
