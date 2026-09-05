<?php

declare(strict_types=1);

namespace Drupal\sqlite\Driver\Database\sqlite;

use Drupal\Core\Database\ConnectionParametersBase;

/**
 * Connection parameters object for an SQLite database.
 */
class ConnectionParameters extends ConnectionParametersBase {

  public function __construct(
    public string $driver,
    public string $namespace,
    public string $autoload,
    public string $database,
    public string $prefix = '',
    public string $module = 'sqlite',
    public readonly array $init_commands = [],
    public readonly array $pdo = [],
    array $unknownOptions = [],
  ) {
    parent::__construct($unknownOptions);
  }

  /**
   * {@inheritdoc}
   */
  public static function processOptionsArray(array $options): array {
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

    // Additional keys found in the options array, that are not first-class
    // properties, will be stored in the $unknownOptions property.
    $unknownOptions = array_diff_key($options, array_flip(static::getNames()));
    foreach (array_keys($unknownOptions) as $key) {
      unset($options[$key]);
    }
    $options['unknownOptions'] = $unknownOptions;

    return $options;
  }

}
