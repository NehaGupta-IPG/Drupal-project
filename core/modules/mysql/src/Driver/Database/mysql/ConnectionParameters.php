<?php

declare(strict_types=1);

namespace Drupal\mysql\Driver\Database\mysql;

use Drupal\Core\Database\ConnectionParametersBase;
use Pdo\Mysql;

/**
 * Connection parameters object for a MySQL database.
 *
 * @phpstan-import-type UnprocessedConnectionInfoArray from \Drupal\Core\Database\Database
 * @phpstan-import-type ConnectionInfoBaseArray from \Drupal\Core\Database\Database
 *
 * @phpstan-type MysqlConnectionInfoArray ConnectionInfoBaseArray&array{
 *   'username': string,
 *   'password': string,
 *   'host': string,
 *   'port': int,
 *   'unix_socket'?: string,
 *   'init_commands': array<string|int,string|int>,
 *   'pdo': array<mixed>,
 *   'collation'?: string,
 *   'isolation_level'?: string,
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
    #[\SensitiveParameter] public readonly string $username,
    #[\SensitiveParameter] public readonly string $password,
    #[\SensitiveParameter] public readonly string $host,
    #[\SensitiveParameter] public readonly int $port,
    #[\SensitiveParameter] public readonly array $init_commands,
    #[\SensitiveParameter] public readonly array $pdo,
    #[\SensitiveParameter] array $unknownOptions,
    #[\SensitiveParameter] public readonly ?string $unix_socket = NULL,
    #[\SensitiveParameter] public readonly ?string $isolation_level = NULL,
    #[\SensitiveParameter] public readonly ?string $collation = NULL,
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
   * @return MysqlConnectionInfoArray
   *   The processed options.
   *
   * @internal
   */
  public static function processOptionsArray(array $options): array {
    $options = parent::processOptionsArray($options);

    $options['port'] ??= 3306;

    // Allow PDO options to be overridden.
    $options += [
      'pdo' => [],
    ];
    $options['pdo'] += [
      \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    ];
    // So we don't have to mess around with cursors and unbuffered queries by
    // default.
    $options['pdo'] += [
      Mysql::ATTR_USE_BUFFERED_QUERY => TRUE,
    ];
    // Make sure MySQL returns all matched rows on update queries including
    // rows that actually didn't have to be updated because the values didn't
    // change. This matches common behavior among other database systems.
    $options['pdo'] += [
      Mysql::ATTR_FOUND_ROWS => TRUE,
    ];
    // Because MySQL's prepared statements skip the query cache.
    $options['pdo'] += [
      \PDO::ATTR_EMULATE_PREPARES => TRUE,
    ];
    // Limit SQL to a single statement like mysqli.
    $options['pdo'] += [
      Mysql::ATTR_MULTI_STATEMENTS => FALSE,
    ];
    // Convert numeric values to strings when fetching. In PHP 8.1,
    // \PDO::ATTR_EMULATE_PREPARES now behaves the same way as non emulated
    // prepares and returns integers. See https://externals.io/message/113294
    // for further discussion.
    $options['pdo'] += [
      \PDO::ATTR_STRINGIFY_FETCHES => TRUE,
    ];

    // Set MySQL init_commands if not already defined.  Default Drupal's MySQL
    // behavior to conform more closely to SQL standards.  This allows Drupal
    // to run almost seamlessly on many different kinds of database systems.
    // These settings force MySQL to behave the same as postgresql, or sqlite
    // in regards to syntax interpretation and invalid data handling.  See
    // https://www.drupal.org/node/344575 for further discussion. Also, as MySQL
    // 5.5 changed the meaning of TRADITIONAL we need to spell out the modes one
    // by one.
    $options['init_commands'] ??= [];
    $options['init_commands']['sql_mode'] ??= "SET sql_mode = 'ANSI,TRADITIONAL'";

    /** @var ConnectionInfoBaseArray $options */
    if (!empty($options['isolation_level'])) {
      assert(is_string($options['isolation_level']));
      $options['init_commands']['isolation_level'] ??= 'SET SESSION TRANSACTION ISOLATION LEVEL ' . strtoupper(($options['isolation_level']));
    }

    return $options;
  }

}
