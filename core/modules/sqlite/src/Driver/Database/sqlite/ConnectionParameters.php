<?php

declare(strict_types=1);

namespace Drupal\sqlite\Driver\Database\sqlite;

use Drupal\Core\Database\ConnectionParametersBase;

/**
 * Connection parameters object for an SQLite database.
 */
class ConnectionParameters extends ConnectionParametersBase {

  /**
   * {@inheritdoc}
   */
  public static function processOptionsArray(array $options): array {
    // Initialize 'init_commands' and 'pdo' if not defined in the input array.
    $options = array_merge([
      'module' => 'sqlite',
      'init_commands' => [],
      'pdo' => [],
    ], $options);

    // Allow PDO options to be overridden.
    $options['pdo'] += [
      \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
      // Convert numeric values to strings when fetching.
      \PDO::ATTR_STRINGIFY_FETCHES => TRUE,
    ];

    // Set SQLite init_commands if not already defined. Enable the Write-Ahead
    // Logging (WAL) for SQLite. See https://www.drupal.org/node/2348137 and
    // https://www.sqlite.org/wal.html.
    $options['init_commands'] += [
      'wal' => "PRAGMA journal_mode=WAL",
    ];

    return parent::processOptionsArray($options);
  }

  public function __construct(
    public string $driver,
    public string $namespace,
    #[\SensitiveParameter] public string $autoload,
    #[\SensitiveParameter] public string $database,
    #[\SensitiveParameter] public string $prefix,
    public string $module,
    #[\SensitiveParameter] public readonly array $init_commands,
    #[\SensitiveParameter] public readonly array $pdo,
    #[\SensitiveParameter] array $unknownOptions,
  ) {
    parent::__construct($unknownOptions);
  }

}
