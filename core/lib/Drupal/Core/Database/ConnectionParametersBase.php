<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

/**
 * Base class for connection parameters objects.
 *
 * @phpstan-import-type UnprocessedConnectionInfoArray from \Drupal\Core\Database\Database
 * @phpstan-import-type ConnectionInfoBaseArray from \Drupal\Core\Database\Database
 *
 * @implements \ArrayAccess<string|int,int|string|float|bool|array|null>
 */
abstract class ConnectionParametersBase implements ConnectionParametersInterface, \ArrayAccess {

  // phpcs:disable
  /**
   * {@inheritdoc}
   */
  public string $driver {
    set(string $value) {
      if (isset($this->driver) && !$this->arrayAccessPropertySetting) {
        throw new \RuntimeException('The $driver property is already set');
      }
      $this->driver = $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public string $module {
    set(string $value) {
      if (isset($this->module) && !$this->arrayAccessPropertySetting) {
        throw new \RuntimeException('The $module property is already set');
      }
      $this->module = $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public string $namespace {
    set(string $value) {
      if (isset($this->namespace) && !$this->arrayAccessPropertySetting) {
        throw new \RuntimeException('The $namespace property is already set');
      }
      $this->namespace = $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public string $autoload {
    set(string $value) {
      if (isset($this->autoload) && !$this->arrayAccessPropertySetting) {
        throw new \RuntimeException('The $autoload property is already set');
      }
      $this->autoload = $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public string $database {
    set(string $value) {
      if (isset($this->database) && !$this->arrayAccessPropertySetting) {
        throw new \RuntimeException('The $database property is already set');
      }
      $this->database = $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public string $prefix {
    set(string $value) {
      if (isset($this->prefix) && !$this->arrayAccessPropertySetting) {
        throw new \RuntimeException('The $prefix property is already set');
      }
      $this->prefix = $value;
    }
  }
  // phpcs:enable

  /**
   * Allows BC to access publicly a property as an array element for writing.
   *
   * @internal
   */
  protected bool $arrayAccessPropertySetting = FALSE;

  /**
   * Constructor.
   *
   * @param array<string|int,scalar|array|null> $unknownOptions
   *   The options that are not relevant for the driver.
   */
  public function __construct(
    // Unknown connection options. The user input may include options that the
    // driver cannot handle, but we still can provide access to them.
    protected array $unknownOptions = [],
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function processOptionsArray(array $options): array {
    $options = array_merge([
      'driver' => '',
      'module' => explode('\\', static::class)[1],
      'namespace' => '',
      'autoload' => '',
      'database' => '',
      'prefix' => '',
    ], $options);

    // Additional keys found in the options array, that are not first-class
    // properties, will be stored in the $unknownOptions property.
    $unknownOptions = array_diff_key($options, array_flip(static::getNames()));
    foreach (array_keys($unknownOptions) as $key) {
      unset($options[$key]);
    }
    $options['unknownOptions'] = $unknownOptions;

    /** @var ConnectionInfoBaseArray $options */
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public static function getNames(): array {
    $properties = [];
    foreach (new \ReflectionClass(static::class)->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
      $properties[] = $property->getName();
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getConnectionClass(): string {
    /** @var class-string<Connection> $class */
    $class = $this->namespace . '\\Connection';
    return $class;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    $tmp = [];
    foreach (static::getNames() as $property) {
      $tmp[$property] = $this->{$property};
    }
    /** @var ConnectionInfoBaseArray $tmp */
    $tmp = array_merge($this->unknownOptions, $tmp);
    return $tmp;
  }

  /**
   * {@inheritdoc}
   */
  public function asUrl(): string {
    if (property_exists($this, 'username') && is_string($this->username)) {
      $user = $this->username;
    }
    elseif (isset($this->unknownOptions['username']) && is_string($this->unknownOptions['username'])) {
      $user = $this->unknownOptions['username'];
    }
    else {
      $user = '';
    }

    if (property_exists($this, 'password') && is_string($this->password)) {
      $password = $this->password;
    }
    elseif (isset($this->unknownOptions['password']) && is_string($this->unknownOptions['password'])) {
      $password = $this->unknownOptions['password'];
    }
    else {
      $password = '';
    }

    if ($user !== '') {
      if ($password !== '') {
        $user .= ':' . $password;
      }
      $user .= '@';
    }

    if (property_exists($this, 'host') && is_string($this->host)) {
      $host = $this->host;
    }
    elseif (isset($this->unknownOptions['host']) && is_string($this->unknownOptions['host'])) {
      $host = $this->unknownOptions['host'];
    }
    else {
      $host = 'localhost';
    }

    $url = $this->driver . '://' . $user . $host;

    if (property_exists($this, 'port') && (is_string($this->port) || is_numeric($this->port))) {
      $url .= ':' . (string) $this->port;
    }
    elseif (isset($this->unknownOptions['port']) && (is_string($this->unknownOptions['port']) || is_numeric($this->unknownOptions['port']))) {
      $url .= ':' . (string) $this->unknownOptions['port'];
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
   * {@inheritdoc}
   */
  public static function parseUrlPartsIntoOptions(array $urlComponents, array $queryComponents): array {
    if (!isset($urlComponents['scheme'], $urlComponents['host'], $urlComponents['path'])) {
      throw new \InvalidArgumentException("The database connection URL is invalid. The minimum requirement is: 'driver://host/database'");
    }

    // Remove leading slash from the URL path.
    if (str_starts_with($urlComponents['path'], '/')) {
      $urlComponents['path'] = substr($urlComponents['path'], 1);
    }

    $options = [
      'driver' => $urlComponents['scheme'],
      'database' => $urlComponents['path'],
    ];

    if (isset($urlComponents['user'])) {
      $options['username'] = $urlComponents['user'];
    }

    if (isset($urlComponents['pass'])) {
      $options['password'] = $urlComponents['pass'];
    }

    $options['host'] = $urlComponents['host'];

    if (isset($urlComponents['port'])) {
      $options['port'] = $urlComponents['port'];
    }

    if (!empty($urlComponents['fragment'])) {
      $options['prefix'] = $urlComponents['fragment'];
    }

    $return = array_merge($options, $queryComponents);
    /** @var UnprocessedConnectionInfoArray $return */
    return $return;
  }

  /**
   * Gets an unknownOptions element as a property value.
   */
  public function __get(string $name): mixed {
    if (array_key_exists($name, $this->unknownOptions)) {
      return $this->unknownOptions[$name];
    }
    throw new \RuntimeException("There is no '{$name}' property or undefined option in " . get_class($this));
  }

  /**
   * Check if an unknownOptions element can be provided as a property value.
   */
  public function __isset(string $name): bool {
    return array_key_exists($name, $this->unknownOptions);
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
    $offset = (string) $offset;
    if (property_exists($this, $offset)) {
      return TRUE;
    }
    return array_key_exists($offset, $this->unknownOptions);
  }

  /**
   * This is for BC support only.
   */
  public function &offsetGet(mixed $offset): mixed {
    $offset = (string) $offset;
    if (property_exists($this, $offset)) {
      // Need to use a temp variable and not $this->{$offset} directly here
      // because if properties are defined as readonly, the & reference
      // operator in the method name would result in an attempt to modify the
      // property value.
      $tmp = $this->{$offset};
      assert(is_null($tmp) || is_bool($tmp) || is_string($tmp) || is_numeric($tmp) || is_array($tmp));
      return $tmp;
    }
    return $this->unknownOptions[$offset];
  }

  /**
   * This is for BC support only.
   */
  public function offsetSet(mixed $offset, mixed $value): void {
    $offset = (string) $offset;
    assert(is_null($value) || is_bool($value) || is_string($value) || is_numeric($value));
    if (property_exists($this, $offset)) {
      $this->arrayAccessPropertySetting = TRUE;
      $this->{$offset} = $value;
      $this->arrayAccessPropertySetting = FALSE;
      return;
    }
    $this->unknownOptions[$offset] = $value;
  }

  /**
   * This is for BC support only.
   */
  public function offsetUnset(mixed $offset): void {
    $offset = (string) $offset;
    if (property_exists($this, $offset)) {
      $this->{$offset} = NULL;
      return;
    }
    unset($this->unknownOptions[$offset]);
  }

}
