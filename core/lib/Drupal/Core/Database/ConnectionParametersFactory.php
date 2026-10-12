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

    // Without driver name, or database, we cannot proceed.
    if (!isset($options['driver'])) {
      throw new \InvalidArgumentException("Database driver name missing from the connection options");
    }
    if (!isset($options['database'])) {
      throw new \InvalidArgumentException("Database name missing from the connection options");
    }

    // Force port to an int if it exists.
    if (isset($options['port'])) {
      $options['port'] = (int) $options['port'];
    }

    // If no module passed in, we first try to determine it from the namespace,
    // and if that is missing on not relevant, we assume the module to have the
    // same name of the driver.
    if (empty($options['module'])) {
      if (!empty($options['namespace'])) {
        $module = explode('\\', $options['namespace'])[1];
        if ($module !== '' && $module !== 'Core') {
          $options['module'] = $module;
        }
      }
      if (empty($options['module'])) {
        $options['module'] = strtolower($options['driver']);
      }
    }

    // If no namespace passed in, build it from driver and module.
    if (empty($options['namespace']) || str_starts_with($options['namespace'], 'Drupal\\Core\\Database\\Driver\\')) {
      $options['namespace'] = "Drupal\\{$options['module']}\\Driver\\Database\\{$options['driver']}";
    }

    // If no autoload passed in, build it from driver information.
    // For performance, hardcode core drivers data.
    if (empty($options['autoload'])) {
      $options['autoload'] = match (trim($options['namespace'], '\\')) {
        "Drupal\\mysql\\Driver\\Database\\mysql" => "core/modules/mysql/src/Driver/Database/mysql/",
        "Drupal\\mysqli\\Driver\\Database\\mysqli" => "core/modules/mysqli/src/Driver/Database/mysqli/",
        "Drupal\\pgsql\\Driver\\Database\\pgsql" => "core/modules/pgsql/src/Driver/Database/pgsql/",
        "Drupal\\sqlite\\Driver\\Database\\sqlite" => "core/modules/sqlite/src/Driver/Database/sqlite/",
        default => NULL,
      };
    }
    if (empty($options['autoload'])) {
      /** @var \Drupal\Core\Extension\DatabaseDriver $driver */
      $driver = Database::getDriverList()
        ->includeTestDrivers(TRUE)
        ->get($options['namespace']);
      $options['autoload'] = $driver->getPath() . DIRECTORY_SEPARATOR;
    }

    // Since the database driver is provided by a module, then its code may
    // need to be instantiated prior to when the module's root namespace is
    // added to the autoloader, because that happens during service container
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
    // @todo in drupal:13.0.0 remove the wrapper.
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
   * @param bool|null $includeTestDrivers
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
  final public static function createFromUrl(string $url, ?bool $includeTestDrivers = NULL): ConnectionParametersInterface {
    // Check that the URL is well formed, starting with 'scheme://', where
    // 'scheme' is a database driver name.
    if (preg_match('/^(.*):\/\//', $url, $matches) !== 1) {
      throw new \InvalidArgumentException("Missing scheme in URL '$url'");
    }
    $driverName = $matches[1];

    // Explode the URL and the URL query string into their parts.
    $urlParts = parse_url($url);
    assert(is_array($urlParts));
    parse_str($urlParts['query'] ?? '', $options);
    unset($urlParts['query']);

    // Use the driver name as the module name when the module name is not
    // provided. Build the namespace and find the autoload directory from the
    // module information.
    $options['module'] ??= strtolower($driverName);
    assert(is_string($options['module']));
    $options['namespace'] = "Drupal\\{$options['module']}\\Driver\\Database\\{$driverName}";
    /** @var \Drupal\Core\Extension\DatabaseDriver $driver */
    $driver = Database::getDriverList()
      ->includeTestDrivers($includeTestDrivers)
      ->get($options['namespace']);
    $options['autoload'] = $driver->getPath() . DIRECTORY_SEPARATOR;

    // Set up an additional autoloader. We don't use the main autoloader as
    // this method can be called before Drupal is installed and is never
    // called during regular runtime.
    $additionalClassLoader = new ClassLoader();
    $additionalClassLoader->addPsr4($options['namespace'] . '\\', $driver->getPath());
    $additionalClassLoader->register();
    $connectionClass = $options['namespace'] . '\\Connection';
    if (!class_exists($connectionClass)) {
      throw new \InvalidArgumentException("Can not convert '$url' to a database connection, class '$connectionClass' does not exist");
    }

    // When the database driver is extending another database driver, then
    // add autoload info for the parent database driver as well.
    $autoloadInfo = $driver->getAutoloadInfo();
    if (isset($autoloadInfo['dependencies'])) {
      foreach ($autoloadInfo['dependencies'] as $dependency) {
        $additionalClassLoader->addPsr4($dependency['namespace'] . '\\', $dependency['autoload']);
      }
      $options['dependencies'] = $autoloadInfo['dependencies'];
    }

    $additionalClassLoader->register(TRUE);

    /** @var class-string<ConnectionParametersInterface> $class */
    $class = $options['namespace'] . '\\ConnectionParameters';

    if (class_exists($class)) {
      $options = $class::parseUrlPartsIntoOptions($urlParts, $options);
    }
    else {
      $options = ConnectionParametersBase::parseUrlPartsIntoOptions($urlParts, $options);
    }

    return self::createFromOptionsArray($options);
  }

}
