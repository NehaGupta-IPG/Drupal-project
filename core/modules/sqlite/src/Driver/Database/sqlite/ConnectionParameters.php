<?php

declare(strict_types=1);

namespace Drupal\sqlite\Driver\Database\sqlite;

use Drupal\Core\Database\ConnectionParametersBase;

/**
 * Connection parameters object for an SQLite database.
 */
class ConnectionParameters extends ConnectionParametersBase {

  /**
   * The list of parameters expected for an SQLite connection.
   *
   * Additional keys found in the options array will be stored in the
   * $optionsBackfill property.
   */
  protected const CANONICAL_PARAMETERS = [
    'driver',
    'namespace',
    'autoload',
    'database',
    'prefix',
    'module',
    'username',
    'password',
    'host',
    'port',
    'dependencies',
    'init_commands',
    'pdo',
  ];

  public function __construct(
    public readonly string $driver,
    public readonly string $namespace,
    public readonly string $autoload,
    public readonly string $database,
    public readonly string $prefix = '',
    public readonly ?string $module = NULL,
    #[\SensitiveParameter]
    public readonly ?string $username = NULL,
    #[\SensitiveParameter]
    public readonly ?string $password = NULL,
    #[\SensitiveParameter]
    public readonly ?string $host = NULL,
    #[\SensitiveParameter]
    public readonly ?int $port = NULL,
    public readonly ?array $dependencies = NULL,
    public readonly array $init_commands = [],
    public readonly array $pdo = [],
    // Backfill connection options. This is meant for BC to ensure options
    // unexpected by the driver can still be handled.
    // @todo remove in drupal:43.0.0.
    array $optionsBackfill = [],
  ) {
    parent::__construct($optionsBackfill);
  }

  /**
   * {@inheritdoc}
   */
  public static function processOptionsArray(array $options): array {
    $options = array_merge(['init_commands' => [], 'pdo' => []], $options);

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

    $optionsBackfill = array_diff_key($options, array_flip(static::CANONICAL_PARAMETERS));
    foreach (array_keys($optionsBackfill) as $key) {
      unset($options[$key]);
    }
    $options['optionsBackfill'] = $optionsBackfill;

    return $options;
  }

}
