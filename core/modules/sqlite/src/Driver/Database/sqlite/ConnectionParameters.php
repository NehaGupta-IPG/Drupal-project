<?php

declare(strict_types=1);

namespace Drupal\sqlite\Driver\Database\sqlite;

use Drupal\Core\Database\ConnectionParametersBase;

/**
 * Connection parameters object for an SQLite database.
 */
class ConnectionParameters extends ConnectionParametersBase {

  /**
   * PDO options.
   */
  public array $pdo = [];

  /**
   * Connection initialization commands.
   */
  public array $initCommands = [];

  /**
   * Tables collation.
   */
  public string $collation;

  /**
   * Transaction isolation level.
   */
  public int|string $isolationLevel;

}
