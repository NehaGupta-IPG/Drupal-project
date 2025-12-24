<?php

declare(strict_types=1);

namespace Drupal\sqlite\Driver\Database\sqlite;

use Drupal\Core\Database\ConnectionParametersBase;

/**
 * Connection parameters object for an SQLite database.
 */
class ConnectionParameters extends ConnectionParametersBase {

  public function __construct(array $options) {
    parent::__construct(
      $options['driver'],
      $options['namespace'],
      $options['autoload'],
      $options['database'],
      $options['prefix'] ?? '',
      $options['module'] ?? NULL,
      $options['username'] ?? NULL,
      $options['password'] ?? NULL,
      $options['host'] ?? NULL,
      $options['port'] ?? NULL,
      $options['dependencies'] ?? NULL,
    );
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

    return $options;
  }

}
