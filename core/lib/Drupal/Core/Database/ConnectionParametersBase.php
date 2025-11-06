<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

use Composer\Autoload\ClassLoader;

/**
 * Base class for connection parameters objects.
 */
abstract class ConnectionParametersBase implements ConnectionParametersInterface, \ArrayAccess {

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
  ) {
  }

  /**
   * Process the configuration file for database information.
   *
   * @param ConnectionInfoArray|list<ConnectionInfoArray> $info
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
   */
  final public static function parseConnectionArray(array $info, ?ClassLoader $class_loader = NULL, ?string $app_root = NULL): ConnectionParametersInterface {
    // If there is no "driver" property, then we assume it's an array of
    // possible connections for this target. Pick one at random. That allows
    // us to have, for example, multiple replica servers.
    if (empty($info['driver'])) {
      $info_pick = $info[mt_rand(0, count($info) - 1)];
    }

    /** @var ConnectionInfoArray $options */
    $options = $info_pick ?? $info;

    // Force port to an int if it exists.
    if (isset($options['port'])) {
      $options['port'] = (int) $options['port'];
    }

    // Backwards compatibility layer for Drupal 8 style database connection
    // arrays. Those have the wrong 'namespace' key set, or not set at all
    // for core supported database drivers.
    if (empty($options['namespace']) || str_starts_with($options['namespace'], 'Drupal\\Core\\Database\\Driver\\')) {
      switch (strtolower($options['driver'])) {
        case 'mysql':
          $options['namespace'] = 'Drupal\\mysql\\Driver\\Database\\mysql';
          break;

        case 'pgsql':
          $options['namespace'] = 'Drupal\\pgsql\\Driver\\Database\\pgsql';
          break;

        case 'sqlite':
          $options['namespace'] = 'Drupal\\sqlite\\Driver\\Database\\sqlite';
          break;
      }
    }

    // Backwards compatibility layer for Drupal 8 style database connection
    // arrays. Those do not have the 'autoload' key set for core database
    // drivers.
    if (empty($options['autoload']) && isset($options['namespace'])) {
      switch (trim($options['namespace'], '\\')) {
        case "Drupal\\mysql\\Driver\\Database\\mysql":
          $options['autoload'] = "core/modules/mysql/src/Driver/Database/mysql/";
          break;

        case "Drupal\\pgsql\\Driver\\Database\\pgsql":
          $options['autoload'] = "core/modules/pgsql/src/Driver/Database/pgsql/";
          break;

        case "Drupal\\sqlite\\Driver\\Database\\sqlite":
          $options['autoload'] = "core/modules/sqlite/src/Driver/Database/sqlite/";
          break;
      }
    }

    // If the database driver is provided by a module, then its code may need
    // to be instantiated prior to when the module's root namespace is added
    // to the autoloader, because that happens during service container
    // initialization but the container definition is likely in the database.
    // Therefore, allow the connection info to specify an autoload directory
    // for the driver.
    if ($class_loader && $app_root) {
      $class_loader->setPsr4($options['namespace'] . '\\', $app_root . DIRECTORY_SEPARATOR . $options['autoload']);

      // When the database driver is extending from other database drivers,
      // then add autoload directory for the parent database driver modules
      // as well.
      if (!empty($options['dependencies'])) {
        foreach ($options['dependencies'] as $dependency) {
          $class_loader->setPsr4($dependency['namespace'] . '\\', $app_root . DIRECTORY_SEPARATOR . $dependency['autoload']);
        }
      }
    }

    return self::createFromOptionsArray($options);
  }

  /**
   * Converts a connection options array to a ConnectionParameters object.
   *
   * @param ConnectionInfoArray $connection_options
   *   An array of options for the connection.
   *
   * @return ConnectionParametersInterface
   *   A ConnectionParameters object.
   */
  final public static function createFromOptionsArray(array $connection_options): ConnectionParametersInterface {
    $connection_options = array_merge([
      'namespace' => '',
      'autoload' => '',
    ], $connection_options);

    $class = $connection_options['namespace'] . '\\ConnectionParameters';

    if (class_exists($class)) {
      $connection_options = $class::processOptionsArray($connection_options);
      return new $class(...$connection_options);
    }

    return new class ($connection_options) extends ConnectionParametersBase {

      /**
       * Backfill connection options.
       */
      private array $optionsBackfill;

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
        $this->optionsBackfill = array_merge($options, get_object_vars($this));
      }

      /**
       * {@inheritdoc}
       */
      public function getParameters(): array {
        return $this->optionsBackfill;
      }

      /**
       * {@inheritdoc}
       */
      public function toArray(): array {
        return $this->optionsBackfill;
      }

      /**
       * This is for BC support only.
       */
      public function offsetExists(mixed $offset): bool {
        return array_key_exists($offset, $this->optionsBackfill);
      }

      /**
       * This is for BC support only.
       */
      public function &offsetGet(mixed $offset): mixed {
        return $this->optionsBackfill[$offset];
      }

      /**
       * This is for BC support only.
       */
      public function offsetSet(mixed $offset, mixed $value): void {
        $this->optionsBackfill[$offset] = $value;
      }

      /**
       * This is for BC support only.
       */
      public function offsetUnset(mixed $offset): void {
        unset($this->optionsBackfill[$offset]);
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
   *   The database connection info.
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
    $url_component_query = $url_components['query'] ?? '';
    parse_str($url_component_query, $query);

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

    $options = $connection_class::createConnectionOptionsFromUrl($url, NULL);

    // Add the necessary information to autoload code.
    // @see \Drupal\Core\Site\Settings::initialize()
    $options['autoload'] = $driver->getPath() . DIRECTORY_SEPARATOR;
    if (isset($autoloadInfo['dependencies'])) {
      $options['dependencies'] = $autoloadInfo['dependencies'];
    }

    return self::createFromOptionsArray($options);
  }

  /**
   * {@inheritdoc}
   */
  public function getParameters(): static|array {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConnectionClass(): string {
    return $this->namespace . '\\Connection';
  }

  /**
   * {@inheritdoc}
   */
  public static function processOptionsArray(array $options): array {
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return get_object_vars($this);
  }

  /**
   * {@inheritdoc}
   */
  public function asUrl(): string {
    $user = '';
    if (isset($this->username)) {
      $user = $this->username;
      if (isset($this->password)) {
        $user .= ':' . $this->password;
      }
      $user .= '@';
    }

    $host = empty($this->host) ? 'localhost' : $this->host;

    $url = $this->driver . '://' . $user . $host;

    if (isset($this->port)) {
      $url .= ':' . $this->port;
    }

    $url .= '/' . $this->database;

    // Add the module.
    $module = $this->module ?? explode('\\', $this->namespace)[1];
    $url .= '?module=' . $module;

    if ($this->prefix !== '') {
      $url .= '#' . $this->prefix;
    }

    return $url;
  }

  /**
   * Prevents the connection parameters from being serialized.
   *
   * @throws \LogicException
   */
  public function __serialize(): array {
    throw new \LogicException(get_class($this) . ' is not serializable. This probably means you are serializing an object that has an indirect reference to the database connection parameters. Adjust your code so that is not necessary. Alternatively, look at DependencySerializationTrait as a temporary solution.');
  }

  /**
   * This is for BC support only.
   */
  public function offsetExists(mixed $offset): bool {
    return property_exists($this, $offset);
  }

  /**
   * This is for BC support only.
   */
  public function offsetGet(mixed $offset): mixed {
    return $this->{$offset};
  }

  /**
   * This is for BC support only.
   */
  public function offsetSet(mixed $offset, mixed $value): void {
    $this->{$offset} = $value;
  }

  /**
   * This is for BC support only.
   */
  public function offsetUnset(mixed $offset): void {
    $this->{$offset} = NULL;
  }

}
