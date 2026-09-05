<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

/**
 * Base class for connection parameters objects.
 */
abstract class ConnectionParametersBase implements ConnectionParametersInterface, \ArrayAccess {

  // phpcs:disable
  /**
   * {@inheritdoc}
   */
  public string $driver {
    set(string $value) {
      if (isset($this->driver)) {
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
      if (isset($this->module)) {
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
      if (isset($this->namespace)) {
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
      if (isset($this->autoload)) {
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
      if (isset($this->database)) {
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
      if (isset($this->prefix)) {
        throw new \RuntimeException('The $prefix property is already set');
      }
      $this->prefix = $value;
    }
  }
  // phpcs:enable

  public function __construct(
    // Unknown connection options. The user input may include options that the
    // driver cannot handle, but we still can provide access to them.
    protected array $unknownOptions = [],
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getNames(): array {
    $properties = [];
    foreach ((new \ReflectionClass(static::class))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
      $name = $property->getName();
      if ($name !== 'unknownOptions') {
        $properties[] = $property->getName();
      }
    }
    return $properties;
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
    $tmp = get_object_vars($this);
    unset($tmp['unknownOptions']);
    return array_merge($this->unknownOptions, $tmp);
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
    if (property_exists($this, $offset)) {
      return TRUE;
    }
    return array_key_exists($offset, $this->unknownOptions);
  }

  /**
   * This is for BC support only.
   */
  public function &offsetGet(mixed $offset): mixed {
    if (property_exists($this, $offset)) {
      // Need to use a temp variable and not $this->{$offset} directly here
      // because if properties are defined as readonly, the & reference
      // operator in the method name would result in an attempt to modify the
      // property value.
      $tmp = $this->{$offset};
      return $tmp;
    }
    return $this->unknownOptions[$offset];
  }

  /**
   * This is for BC support only.
   */
  public function offsetSet(mixed $offset, mixed $value): void {
    if (property_exists($this, $offset)) {
      $this->{$offset} = $value;
      return;
    }
    $this->unknownOptions[$offset] = $value;
  }

  /**
   * This is for BC support only.
   */
  public function offsetUnset(mixed $offset): void {
    if (property_exists($this, $offset)) {
      $this->{$offset} = NULL;
      return;
    }
    unset($this->unknownOptions[$offset]);
  }

}
