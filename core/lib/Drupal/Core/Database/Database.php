<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

use Composer\Autoload\ClassLoader;
use Drupal\Core\Database\Event\StatementEvent;
use Drupal\Core\Extension\DatabaseDriverList;
use Drupal\Core\Cache\NullBackend;

/**
 * Primary front-controller for the database system.
 *
 * This class is un-extendable. It acts to encapsulate all control and
 * shepherding of database connections into a single location without the use of
 * globals.
 *
 * @phpstan-type UnprocessedConnectionInfoArray array{
 *   'driver': string,
 *   'autoload'?: string,
 *   'namespace'?: string,
 *   'database': string,
 *   'username'?: string,
 *   'password'?: string,
 *   'host'?: string,
 *   'port'?: string|int,
 *   'prefix'?: string,
 *   'collation'?: string,
 *   'pdo'?: array<mixed>,
 *   'isolation_level'?: int|string,
 *   'init_commands'?: list<string>,
 *   'dependencies'?: array<string,array{
 *     'autoload': string,
 *     'namespace': string,
 *   }>,
 * }
 * @phpstan-type ConnectionInfoArray array{
 *   'driver': string,
 *   'autoload': string,
 *   'namespace': string,
 *   'database': string,
 *   'username'?: string,
 *   'password'?: string,
 *   'host'?: string,
 *   'port'?: string|int,
 *   'prefix': string,
 *   'collation'?: string,
 *   'pdo'?: array<mixed>,
 *   'isolation_level'?: int|string,
 *   'init_commands'?: list<string>,
 *   'dependencies'?: array<string,array{
 *     'autoload': string,
 *     'namespace': string,
 *   }>,
 * }
 * @phpstan-import-type DatabaseLogEntry from \Drupal\Core\Database\Log
 *
 * @final
 */
abstract class Database {

  /**
   * A nested array of active connections, keyed by database key and target.
   *
   * @var array<string|int,array<string|int,\Drupal\Core\Database\Connection>>
   */
  protected static array $connections = [];

  /**
   * A processed copy of the database connection information from settings.php.
   *
   * @var array<string|int,array<string|int,ConnectionParametersInterface>>
   */
  protected static array $databaseInfo = [];

  /**
   * A list of key/target credentials to simply ignore.
   *
   * @var array<string|int,array<string|int,true>>
   */
  protected static array $ignoreTargets = [];

  /**
   * The key of the currently active database connection.
   */
  protected static string|int $activeKey = 'default';

  /**
   * An array of active query log objects.
   *
   * Every connection has one and only one logger object for all targets and
   * logging keys.
   *
   * @var array<string|int,\Drupal\Core\Database\Log>
   */
  protected static array $logs = [];

  /**
   * Starts logging a given logging key on the specified connection.
   *
   * @param string $logging_key
   *   The logging key to log.
   * @param string $key
   *   (optional) The database connection key for which we want to log. If not
   *   specified, the 'default' connection key will be logged.
   *
   * @return \Drupal\Core\Database\Log
   *   The query log object. Note that the log object does support richer
   *   methods than the few exposed through the Database class, so in some
   *   cases it may be desirable to access it directly.
   *
   * @see \Drupal\Core\Database\Log
   */
  final public static function startLog(string $logging_key, string|int $key = 'default'): Log {
    if (is_int($key)) {
      @trigger_error('Passing an integer value to the $key parameter in ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Pass only string values instead. See https://www.drupal.org/node/3577925', E_USER_DEPRECATED);
    }
    if (empty(self::$logs[$key])) {
      self::$logs[$key] = new Log((string) $key);

      // Every target already active for this connection key needs to have the
      // logging object associated with it.
      if (!empty(self::$connections[$key])) {
        foreach (self::$connections[$key] as $connection) {
          $connection->enableEvents(StatementEvent::all());
          $connection->setLogger(self::$logs[$key]);
        }
      }
    }

    self::$logs[$key]->start($logging_key);
    return self::$logs[$key];
  }

  /**
   * Retrieves the queries logged on for given logging key.
   *
   * This method also ends logging for the specified key. To get the query log
   * to date without ending the logger request the logging object by starting
   * it again (which does nothing to an open log key) and call methods on it as
   * desired.
   *
   * @param string $logging_key
   *   The logging key to log.
   * @param string $key
   *   (optional) The database connection key for which we want to log. If not
   *   specified, the log for the 'default' connection key will be returned.
   *
   * @return list<DatabaseLogEntry>
   *   The query log for the specified logging key and connection.
   *
   * @see \Drupal\Core\Database\Log
   */
  final public static function getLog(string $logging_key, string|int $key = 'default'): array {
    if (is_int($key)) {
      @trigger_error('Passing an integer value to the $key parameter in ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Pass only string values instead. See https://www.drupal.org/node/3577925', E_USER_DEPRECATED);
    }
    if (empty(self::$logs[$key])) {
      return [];
    }
    $queries = self::$logs[$key]->get($logging_key);
    self::$logs[$key]->end($logging_key);
    return $queries;
  }

  /**
   * Gets the connection object for the specified database key and target.
   *
   * @param string $target
   *   (optional) The database target name. If not specified, the 'default'
   *   target will be returned.
   * @param ?string $key
   *   (optional) The database connection key. Defaults to NULL which means the
   *   active key.
   *
   * @return \Drupal\Core\Database\Connection
   *   The corresponding connection object.
   */
  final public static function getConnection(string|int $target = 'default', string|int|null $key = NULL): Connection {
    if (is_int($target)) {
      @trigger_error('Passing an integer value to the $target parameter in ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Pass only string values instead. See https://www.drupal.org/node/3577925', E_USER_DEPRECATED);
    }
    if ($key !== NULL && is_int($key)) {
      @trigger_error('Passing an integer value to the $key parameter in ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Pass only string values instead. See https://www.drupal.org/node/3577925', E_USER_DEPRECATED);
    }
    if (!isset($key)) {
      // By default, we want the active connection, set in setActiveConnection.
      $key = self::$activeKey;
    }
    // If the requested target does not exist, or if it is ignored, we fall back
    // to the default target. The target is typically either "default" or
    // "replica", indicating to use a replica SQL server if one is available. If
    // it's not available, then the default/primary server is the correct server
    // to use.
    if (!empty(self::$ignoreTargets[$key][$target]) || !isset(self::$databaseInfo[$key][$target])) {
      $target = 'default';
    }

    if (!isset(self::$connections[$key][$target])) {
      // If necessary, a new connection is opened.
      self::$connections[$key][$target] = self::openConnection($key, $target);
    }
    return self::$connections[$key][$target];
  }

  /**
   * Determines if there is an active connection.
   *
   * Note that this method will return FALSE if no connection has been
   * established yet, even if one could be.
   *
   * @return bool
   *   TRUE if there is at least one database connection established, FALSE
   *   otherwise.
   */
  final public static function isActiveConnection(): bool {
    return !empty(self::$activeKey) && !empty(self::$connections) && !empty(self::$connections[self::$activeKey]);
  }

  /**
   * Sets the active connection to the specified key.
   *
   * @param string $key
   *   (optional) The database connection key. If not specified, the 'default'
   *   connection key will be activated.
   *
   * @return ?string
   *   The previous database connection key, or NULL if no connection was
   *   previously active.
   */
  final public static function setActiveConnection(string|int $key = 'default'): string|int|null {
    if (is_int($key)) {
      @trigger_error('Passing an integer value to the $key parameter in ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Pass only string values instead. See https://www.drupal.org/node/3577925', E_USER_DEPRECATED);
    }
    if (!empty(self::$databaseInfo[$key])) {
      $old_key = self::$activeKey;
      self::$activeKey = $key;
      return $old_key;
    }
    return NULL;
  }

  /**
   * Process the configuration file for database information.
   *
   * @param UnprocessedConnectionInfoArray|list<UnprocessedConnectionInfoArray> $info
   *   The database connection information, as defined in settings.php. The
   *   structure of this array depends on the database driver it is connecting
   *   to.
   * @param \Composer\Autoload\ClassLoader|null $class_loader
   *   (optional) The class loader. Used for adding the database driver to the
   *   autoloader if $info['autoload'] is set.
   * @param string|null $app_root
   *   (optional) The app root.
   *
   * @return \Drupal\Core\Database\ConnectionParametersInterface
   *   A validated connection info array.
   *
   * @deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Use
   *   ConnectionParametersFactory::createFromArrayOptions() instead.
   *
   * @see https://www.drupal.org/node/7654123
   */
  final public static function parseConnectionInfo(array $info, ?ClassLoader $class_loader = NULL, ?string $app_root = NULL): ConnectionParametersInterface {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Use ConnectionParametersFactory::createFromArrayOptions() instead. See https://www.drupal.org/node/7654123', E_USER_DEPRECATED);
    return ConnectionParametersFactory::createFromOptionsArray($info, $class_loader, $app_root);
  }

  /**
   * Adds database connection information for a given key/target.
   *
   * This method allows to add new connections at runtime.
   *
   * Under normal circumstances the preferred way to specify database
   * credentials is via settings.php. However, this method allows them to be
   * added at arbitrary times, such as during unit tests, when connecting to
   * admin-defined third party databases, etc. Use
   * \Drupal\Core\Database\Database::setActiveConnection to select the
   * connection to use.
   *
   * If the given key/target pair already exists, this method will be ignored.
   *
   * @param string $key
   *   The database key.
   * @param string $target
   *   The database target name.
   * @param \Drupal\Core\Database\ConnectionParametersInterface|ConnectionInfoArray|list<ConnectionInfoArray> $info
   *   The database connection information, as defined in settings.php. The
   *   structure of this array depends on the database driver it is connecting
   *   to.
   * @param \Composer\Autoload\ClassLoader|null $class_loader
   *   (optional) The class loader. Used for adding the database driver to the
   *   autoloader if $info['autoload'] is set.
   * @param string|null $app_root
   *   (optional) The app root.
   *
   * @see \Drupal\Core\Database\Database::setActiveConnection
   */
  final public static function addConnectionInfo(string|int $key, string|int $target, ConnectionParametersInterface|array $info, ?ClassLoader $class_loader = NULL, ?string $app_root = NULL): void {
    if (is_int($key)) {
      @trigger_error('Passing an integer value to the $key parameter in ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Pass only string values instead. See https://www.drupal.org/node/3577925', E_USER_DEPRECATED);
    }
    if (is_int($target)) {
      @trigger_error('Passing an integer value to the $target parameter in ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Pass only string values instead. See https://www.drupal.org/node/3577925', E_USER_DEPRECATED);
    }
    if (!empty(self::$databaseInfo[$key][$target])) {
      return;
    }
    self::$databaseInfo[$key][$target] = is_array($info) ? ConnectionParametersFactory::createFromOptionsArray($info, $class_loader, $app_root) : $info;
  }

  /**
   * Gets information on the specified database connection.
   *
   * @param string $key
   *   (optional) The connection key for which to return information. If not
   *   specified, the 'default' connection key will be returned.
   *
   * @return array<string|int,ConnectionParametersInterface>
   *   An associative array of database information, keyed by target. Defaults
   *   to an empty array.
   */
  final public static function getConnectionInfo(string|int $key = 'default'): array {
    if (is_int($key)) {
      @trigger_error('Passing an integer value to the $key parameter in ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Pass only string values instead. See https://www.drupal.org/node/3577925', E_USER_DEPRECATED);
    }
    if (!empty(self::$databaseInfo[$key])) {
      return self::$databaseInfo[$key];
    }
    return [];
  }

  /**
   * Gets connection information for all available databases.
   *
   * @return array<string|int,array<string|int,ConnectionParametersInterface>>
   *   An associative array of database information for all available database,
   *   keyed by the database key and target. Defaults to an empty array.
   */
  final public static function getAllConnectionInfo(): array {
    return self::$databaseInfo;
  }

  /**
   * Sets connection information for multiple databases.
   *
   * @param array<string|int,array<string|int,UnprocessedConnectionInfoArray>> $databases
   *   A multi-dimensional array specifying database connection parameters, as
   *   defined in settings.php.
   * @param \Composer\Autoload\ClassLoader|null $class_loader
   *   (optional) The class loader. Used for adding the database driver(s) to
   *   the autoloader if $databases[$key][$target]['autoload'] is set.
   * @param string|null $app_root
   *   (optional) The app root.
   */
  final public static function setMultipleConnectionInfo(array $databases, ?ClassLoader $class_loader = NULL, ?string $app_root = NULL): void {
    foreach ($databases as $key => $targets) {
      foreach ($targets as $target => $info) {
        self::addConnectionInfo($key, $target, $info, $class_loader, $app_root);
      }
    }
  }

  /**
   * Rename a connection and its corresponding connection information.
   *
   * @param string $old_key
   *   The old connection key.
   * @param string $new_key
   *   The new connection key.
   *
   * @return bool
   *   TRUE in case of success, FALSE otherwise.
   */
  final public static function renameConnection(string|int $old_key, string|int $new_key): bool {
    if (is_int($old_key)) {
      @trigger_error('Passing an integer value to the $old_key parameter in ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Pass only string values instead. See https://www.drupal.org/node/3577925', E_USER_DEPRECATED);
    }
    if (is_int($new_key)) {
      @trigger_error('Passing an integer value to the $new_key parameter in ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Pass only string values instead. See https://www.drupal.org/node/3577925', E_USER_DEPRECATED);
    }
    if (!empty(self::$databaseInfo[$old_key]) && empty(self::$databaseInfo[$new_key])) {
      // Migrate the database connection information.
      self::$databaseInfo[$new_key] = self::$databaseInfo[$old_key];
      unset(self::$databaseInfo[$old_key]);

      // Migrate over the DatabaseConnection object if it exists.
      if (isset(self::$connections[$old_key])) {
        self::$connections[$new_key] = self::$connections[$old_key];
        unset(self::$connections[$old_key]);
      }

      return TRUE;
    }
    return FALSE;
  }

  /**
   * Remove a connection and its corresponding connection information.
   *
   * @param string $key
   *   The connection key.
   *
   * @return bool
   *   TRUE in case of success, FALSE otherwise.
   */
  final public static function removeConnection(string|int $key): bool {
    if (is_int($key)) {
      @trigger_error('Passing an integer value to the $key parameter in ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Pass only string values instead. See https://www.drupal.org/node/3577925', E_USER_DEPRECATED);
    }
    if (isset(self::$databaseInfo[$key])) {
      self::closeConnection(NULL, $key);
      unset(self::$databaseInfo[$key]);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Opens a connection to the server specified by the given key and target.
   *
   * @param string $key
   *   The database connection key, as specified in settings.php.
   * @param string $target
   *   The database target to open.
   *
   * @throws \Drupal\Core\Database\ConnectionNotDefinedException
   * @throws \Drupal\Core\Database\DriverNotSpecifiedException
   *
   * @return \Drupal\Core\Database\Connection
   *   The opened database connection.
   */
  final protected static function openConnection(string|int $key, string|int $target): Connection {
    if (is_int($key)) {
      @trigger_error('Passing an integer value to the $key parameter in ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Pass only string values instead. See https://www.drupal.org/node/3577925', E_USER_DEPRECATED);
    }
    if (is_int($target)) {
      @trigger_error('Passing an integer value to the $target parameter in ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Pass only string values instead. See https://www.drupal.org/node/3577925', E_USER_DEPRECATED);
    }
    // If the requested database does not exist then it is an unrecoverable
    // error.
    if (!isset(self::$databaseInfo[$key])) {
      throw new ConnectionNotDefinedException('The specified database connection is not defined: ' . $key);
    }

    if (!self::$databaseInfo[$key][$target]->driver) {
      throw new DriverNotSpecifiedException('Driver not specified for this database connection: ' . $key);
    }

    $connection_parameters = self::$databaseInfo[$key][$target];
    $driver_class = $connection_parameters->getConnectionClass();
    $options = property_exists($connection_parameters, 'driver') ? $connection_parameters : $connection_parameters->toArray();
    $client_connection = $driver_class::open($options);
    $new_connection = new $driver_class($client_connection, $options);
    assert($new_connection instanceof Connection);
    $new_connection->setTarget((string) $target);
    $new_connection->setKey((string) $key);

    // If we have any active logging objects for this connection key, we need
    // to associate them with the connection we just opened.
    if (!empty(self::$logs[$key])) {
      $new_connection->enableEvents(StatementEvent::all());
      $new_connection->setLogger(self::$logs[$key]);
    }

    return $new_connection;
  }

  /**
   * Closes a connection to the server specified by the given key and target.
   *
   * @param ?string $target
   *   (optional) The database target name. Defaults to NULL meaning that all
   *   target connections will be closed.
   * @param ?string $key
   *   (optional) The database connection key. Defaults to NULL which means the
   *   active key.
   */
  public static function closeConnection(string|int|null $target = NULL, string|int|null $key = NULL): void {
    if ($target !== NULL && is_int($target)) {
      @trigger_error('Passing an integer value to the $target parameter in ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Pass only string values instead. See https://www.drupal.org/node/3577925', E_USER_DEPRECATED);
    }
    if ($key !== NULL && is_int($key)) {
      @trigger_error('Passing an integer value to the $key parameter in ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Pass only string values instead. See https://www.drupal.org/node/3577925', E_USER_DEPRECATED);
    }
    // Gets the active connection by default.
    if (!isset($key)) {
      $key = self::$activeKey;
    }
    if (isset($target) && isset(self::$connections[$key][$target])) {
      self::$connections[$key][$target]->commitAll();
      unset(self::$connections[$key][$target]);
    }
    elseif (isset(self::$connections[$key])) {
      foreach (self::$connections[$key] as $connection) {
        $connection->commitAll();
      }
      unset(self::$connections[$key]);
    }

    // When last connection for $key is closed, we also stop any active
    // logging.
    if (empty(self::$connections[$key])) {
      unset(self::$logs[$key]);
    }

    // Force garbage collection to run. This ensures that client connection
    // objects and results in the connection being closed are destroyed.
    gc_collect_cycles();
  }

  /**
   * Instructs the system to temporarily ignore a given key/target.
   *
   * At times we need to temporarily disable replica queries. To do so, call
   * this method with the database key and the target to disable. That database
   * key will then always fall back to 'default' for that key, even if it's
   * defined.
   *
   * @param string $key
   *   The database connection key.
   * @param string $target
   *   The target of the specified key to ignore.
   */
  public static function ignoreTarget(string|int $key, string|int $target): void {
    if (is_int($key)) {
      @trigger_error('Passing an integer value to the $key parameter in ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Pass only string values instead. See https://www.drupal.org/node/3577925', E_USER_DEPRECATED);
    }
    if (is_int($target)) {
      @trigger_error('Passing an integer value to the $target parameter in ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Pass only string values instead. See https://www.drupal.org/node/3577925', E_USER_DEPRECATED);
    }
    self::$ignoreTargets[$key][$target] = TRUE;
  }

  /**
   * Converts a URL to a database connection info array.
   *
   * @param string $url
   *   The URL.
   * @param bool|null $include_test_drivers
   *   (optional) Whether to include test extensions. If FALSE, all 'tests'
   *   directories are excluded in the search. When NULL will be determined by
   *   the extension_discovery_scan_tests setting.
   *
   * @return ConnectionParametersInterface
   *   The database connection info.
   *
   * @throws \InvalidArgumentException
   *   Exception thrown when the provided URL does not meet the minimum
   *   requirements.
   * @throws \RuntimeException
   *   Exception thrown when a module provided database driver does not exist.
   *
   * @deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Use
   *   ConnectionParametersFactory::createFromUrl() instead.
   *
   * @see https://www.drupal.org/node/7654123
   */
  public static function convertDbUrlToConnectionInfo(string $url, ?bool $include_test_drivers = NULL): ConnectionParametersInterface {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Use ConnectionParametersFactory::createFromUrl() instead. See https://www.drupal.org/node/7654123', E_USER_DEPRECATED);
    return ConnectionParametersFactory::createFromUrl($url, $include_test_drivers);
  }

  /**
   * Returns the list provider for available database drivers.
   *
   * @return \Drupal\Core\Extension\DatabaseDriverList
   *   The list provider for available database drivers.
   */
  public static function getDriverList(): DatabaseDriverList {
    if (\Drupal::hasContainer() && \Drupal::hasService('extension.list.database_driver')) {
      return \Drupal::service('extension.list.database_driver');
    }
    else {
      return new DatabaseDriverList(DRUPAL_ROOT, 'database_driver', new NullBackend('database_driver'));
    }
  }

  /**
   * Gets database connection info as a URL.
   *
   * @param string $key
   *   (Optional) The database connection key. If not specified, the 'default'
   *   connection key will be returned.
   *
   * @return string
   *   The connection info as a URL.
   *
   * @throws \RuntimeException
   *   When the database connection is not defined.
   *
   * @deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Use
   *   Database::getConnectionInfo()['default']->asUrl() instead.
   *
   * @see https://www.drupal.org/node/7654123
   */
  public static function getConnectionInfoAsUrl(string|int $key = 'default'): string {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Use Database::getConnectionInfo()[\'default\']->asUrl() instead. See https://www.drupal.org/node/7654123', E_USER_DEPRECATED);
    $db_info = static::getConnectionInfo($key);
    if (empty($db_info) || empty($db_info['default'])) {
      throw new \RuntimeException("Database connection $key not defined or missing the 'default' settings");
    }
    return $db_info['default']->asUrl();
  }

  /**
   * Calls commitAll() on all the open connections.
   *
   * If drupal_register_shutdown_function() exists the commit will occur during
   * shutdown so that it occurs at the latest possible moment.
   *
   * @param bool $shutdown
   *   Internal param to denote that the method is being called by
   *   _drupal_shutdown_function().
   *
   * @internal
   *   This method exists only to work around a bug caused by Drupal incorrectly
   *   relying on object destruction order to commit transactions. Xdebug 3.3.0
   *   changes the order of object destruction when the develop mode is enabled.
   *
   * @deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. There is no
   *   replacement.
   *
   * @see https://www.drupal.org/node/3524461
   */
  public static function commitAllOnShutdown(bool $shutdown = FALSE): void {
    // Only soft deprecation (no @trigger_error) to avoid thousands of
    // occurrences per test run. PHPStan reports usage errors anyway.
    static $registered = FALSE;

    if ($shutdown) {
      foreach (self::$connections as $targets) {
        foreach ($targets as $connection) {
          $connection->commitAll();
        }
      }
      return;
    }

    if (!function_exists('drupal_register_shutdown_function')) {
      return;
    }

    if (!$registered) {
      $registered = TRUE;
      drupal_register_shutdown_function('\Drupal\Core\Database\Database::commitAllOnShutdown', TRUE);
    }
  }

}
