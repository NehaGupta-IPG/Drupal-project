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

    return Database::getConnectionParametersFromOptionsArray($options);
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
