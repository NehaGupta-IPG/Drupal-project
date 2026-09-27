<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

use Composer\Autoload\ClassLoader;

/**
 * The factory for database connection parameters.
 *
 * @phpstan-import-type UnprocessedConnectionInfoArray from \Drupal\Core\Database\Database
 * @phpstan-import-type ConnectionInfoBaseArray from \Drupal\Core\Database\Database
 *
 * @final
 */
abstract class ConnectionParametersFactory {

  /**
   * Converts a connection options array to a ConnectionParameters object.
   *
   * @param UnprocessedConnectionInfoArray|list<UnprocessedConnectionInfoArray> $connectionOptions
   *   The database connection information, as defined in settings.php. The
   *   structure of this array depends on the database driver it is connecting
   *   to.
   * @param \Composer\Autoload\ClassLoader|null $classLoader
   *   (optional) The class loader. Used for adding the database driver to the
   *   autoloader if $info['autoload'] is set.
   * @param string|null $appRoot
   *   (optional) The app root.
   *
   * @return \Drupal\Core\Database\ConnectionParametersInterface
   *   The connection parameters.
   */
  final public static function createFromOptionsArray(array $connectionOptions, ?ClassLoader $classLoader = NULL, ?string $appRoot = NULL): ConnectionParametersInterface {
    // If there is no "driver" property, then we assume it's an array of
    // possible connections for this target. Pick one at random. That allows
    // us to have, for example, multiple replica servers.
    if (!isset($connectionOptions['driver']) && count($connectionOptions) > 0) {
      $info_pick = $connectionOptions[mt_rand(0, count($connectionOptions) - 1)];
    }

    /** @var UnprocessedConnectionInfoArray $options */
    $options = $info_pick ?? $connectionOptions;

    // Without driver name, we cannot proceed.
    if (!isset($options['driver'])) {
      throw new \RuntimeException("Database driver name missing from the connection options");
    }

    $options = array_merge([
      'namespace' => '',
      'autoload' => '',
    ], $options);

    // Force port to an int if it exists.
    if (isset($options['port'])) {
      $options['port'] = (int) $options['port'];
    }

    // Backwards compatibility layer for Drupal 8 style database connection
    // arrays. Those have the wrong 'namespace' key set, or not set at all
    // for core supported database drivers.
    if (empty($options['namespace']) || str_starts_with($options['namespace'], 'Drupal\\Core\\Database\\Driver\\')) {
      $driver = strtolower($options['driver']);
      $options['namespace'] = match ($driver) {
        'mysql' => 'Drupal\\mysql\\Driver\\Database\\mysql',
        'pgsql' => 'Drupal\\pgsql\\Driver\\Database\\pgsql',
        'sqlite' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
        default => throw new \RuntimeException("Unknown database driver {$options['driver']}"),
      };
    }

    // Backwards compatibility layer for Drupal 8 style database connection
    // arrays. Those do not have the 'autoload' key set for core database
    // drivers.
    if (empty($options['autoload'])) {
      $options['autoload'] = match (trim($options['namespace'], '\\')) {
        "Drupal\\mysql\\Driver\\Database\\mysql" => "core/modules/mysql/src/Driver/Database/mysql/",
        "Drupal\\pgsql\\Driver\\Database\\pgsql" => "core/modules/pgsql/src/Driver/Database/pgsql/",
        "Drupal\\sqlite\\Driver\\Database\\sqlite" => "core/modules/sqlite/src/Driver/Database/sqlite/",
        default => throw new \RuntimeException("Unknown database driver {$options['driver']}"),
      };
    }

    // Use the namespace to determine the module name when the module name is
    // not provided.
    $options['module'] ??= explode('\\', $options['namespace'])[1] ?? $options['driver'];

    // If the database driver is provided by a module, then its code may need
    // to be instantiated prior to when the module's root namespace is added
    // to the autoloader, because that happens during service container
    // initialization but the container definition is likely in the database.
    // Therefore, allow the connection info to specify an autoload directory
    // for the driver.
    if ($classLoader && $appRoot) {
      $classLoader->setPsr4($options['namespace'] . '\\', $appRoot . DIRECTORY_SEPARATOR . $options['autoload']);

      // When the database driver is extending from other database drivers,
      // then add autoload directory for the parent database driver modules
      // as well.
      if (!empty($options['dependencies'])) {
        foreach ($options['dependencies'] as $dependency) {
          $classLoader->setPsr4($dependency['namespace'] . '\\', $appRoot . DIRECTORY_SEPARATOR . $dependency['autoload']);
        }
      }
    }

    // Return the driver's ConnectionParameters object if the driver supports
    // it, otherwise a fallback wrapper closure.
    // @todo in drupal:43.0.0 remove the wrapper.
    /** @var class-string<ConnectionParametersInterface> $class */
    $class = $options['namespace'] . '\\ConnectionParameters';

    if (class_exists($class)) {
      $options = $class::processOptionsArray($options);
      return new $class(...$options);
    }

    $options = ConnectionParametersBase::processOptionsArray($options);
    /** @var ConnectionInfoBaseArray $options */
    return new class (...$options) extends ConnectionParametersBase {

      public function __construct(
        public string $driver,
        public string $namespace,
        public string $autoload,
        public string $database,
        public string $prefix,
        public string $module,
        array $unknownOptions = [],
      ) {
        parent::__construct($unknownOptions);
      }

    };
  }

  /**
   * Creates an instance from a database URL.
   *
   * @param string $url
   *   The URL.
   * @param bool|null $include_test_drivers
   *   (optional) Whether to include test extensions. If FALSE, all 'tests'
   *   directories are excluded in the search. When NULL will be determined by
   *   the extension_discovery_scan_tests setting.
   *
   * @return ConnectionParametersInterface
   *   The connection parameters.
   *
   * @throws \InvalidArgumentException
   *   Exception thrown when the provided URL does not meet the minimum
   *   requirements.
   * @throws \RuntimeException
   *   Exception thrown when a module provided database driver does not exist.
   */
  final public static function createFromUrl(string $url, ?bool $include_test_drivers = NULL): ConnectionParametersInterface {
    // Check that the URL is well formed, starting with 'scheme://', where
    // 'scheme' is a database driver name.
    if (preg_match('/^(.*):\/\//', $url, $matches) !== 1) {
      throw new \InvalidArgumentException("Missing scheme in URL '$url'");
    }
    $driverName = $matches[1];

    // Determine if the database driver is provided by a module.
    // @todo https://www.drupal.org/project/drupal/issues/3250999. Refactor when
    // all database drivers are provided by modules.
    $url_components = parse_url($url);
    assert(is_array($url_components));
    parse_str($url_components['query'] ?? '', $query);
    unset($url_components['query']);

    // Use the driver name as the module name when the module name is not
    // provided.
    $module = $query['module'] ?? $driverName;
    assert(is_string($module));

    $driverNamespace = "Drupal\\{$module}\\Driver\\Database\\{$driverName}";

    /** @var \Drupal\Core\Extension\DatabaseDriver $driver */
    $driver = Database::getDriverList()
      ->includeTestDrivers($include_test_drivers)
      ->get($driverNamespace);

    // Set up an additional autoloader. We don't use the main autoloader as
    // this method can be called before Drupal is installed and is never
    // called during regular runtime.
    $additional_class_loader = new ClassLoader();
    $additional_class_loader->addPsr4($driverNamespace . '\\', $driver->getPath());
    $additional_class_loader->register();
    $connection_class = $driverNamespace . '\\Connection';
    if (!class_exists($connection_class)) {
      throw new \InvalidArgumentException("Can not convert '$url' to a database connection, class '$connection_class' does not exist");
    }

    // When the database driver is extending another database driver, then
    // add autoload info for the parent database driver as well.
    $autoloadInfo = $driver->getAutoloadInfo();
    if (isset($autoloadInfo['dependencies'])) {
      foreach ($autoloadInfo['dependencies'] as $dependency) {
        $additional_class_loader->addPsr4($dependency['namespace'] . '\\', $dependency['autoload']);
      }
    }

    $additional_class_loader->register(TRUE);

    /** @var class-string<ConnectionParametersInterface> $class */
    $class = $driverNamespace . '\\ConnectionParameters';

    if (class_exists($class)) {
      $options = $class::parseUrlPartsIntoOptions($url_components, $query);
    }
    else {
      $options = ConnectionParametersBase::parseUrlPartsIntoOptions($url_components, $query);
    }

    // Add the necessary information to autoload code.
    // @see \Drupal\Core\Site\Settings::initialize()
    $options['namespace'] = $driverNamespace;
    $options['autoload'] = $driver->getPath() . DIRECTORY_SEPARATOR;
    if (isset($autoloadInfo['dependencies'])) {
      $options['dependencies'] = $autoloadInfo['dependencies'];
    }

    return self::createFromOptionsArray($options);
  }

}
