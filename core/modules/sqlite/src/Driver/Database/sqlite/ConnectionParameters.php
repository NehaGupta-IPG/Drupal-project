<?php

declare(strict_types=1);

namespace Drupal\sqlite\Driver\Database\sqlite;

use Drupal\Core\Database\ConnectionParametersBase;

/**
 * Connection parameters object for an SQLite database.
 *
 * @phpstan-import-type UnprocessedConnectionInfoArray from \Drupal\Core\Database\Database
 * @phpstan-import-type ConnectionInfoBaseArray from \Drupal\Core\Database\Database
 *
 * @phpstan-type SqliteConnectionInfoArray ConnectionInfoBaseArray&array{
 *   'init_commands': array<string|int,string|int>,
 *   'pdo': array<mixed>,
 *   ...
 * }
 */
class ConnectionParameters extends ConnectionParametersBase {

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

  /**
   * Processes an array of connection options for driver specific defaults.
   *
   * This is used internally by the database layer, should not be called
   * otherwise.
   *
   * @param UnprocessedConnectionInfoArray $options
   *   An array of connection options.
   *
   * @return SqliteConnectionInfoArray
   *   The processed options.
   *
   * @internal
   */
  public static function processOptionsArray(array $options): array {
    $options = parent::processOptionsArray($options);

    // Initialize 'init_commands' and 'pdo' if not defined in the input array.
    $options = array_merge([
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

    return $options;
  }

}
