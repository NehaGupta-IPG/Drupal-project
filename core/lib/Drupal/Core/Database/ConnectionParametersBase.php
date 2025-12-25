<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

/**
 * Base class for connection parameters objects.
 */
abstract class ConnectionParametersBase implements ConnectionParametersInterface, \ArrayAccess {

  public function __construct(
    // Backfill connection options. This is meant for BC to ensure options
    // unexpected by the driver can still be handled.
    // @todo remove in drupal:43.0.0.
    protected array $optionsBackfill = [],
  ) {
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
    $tmp = get_object_vars($this);
    unset($tmp['optionsBackfill']);
    return array_merge($this->optionsBackfill, $tmp);
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
   * Gets an optionsBackfill element as a property value.
   */
  public function __get(string $name): mixed {
    if (array_key_exists($name, $this->optionsBackfill)) {
      return $this->optionsBackfill[$name];
    };
    throw new \RuntimeException("There is no '{$name}' property or backfilled property in " . get_class($this));
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
    return array_key_exists($offset, $this->optionsBackfill);
  }

  /**
   * This is for BC support only.
   */
  public function offsetGet(mixed $offset): mixed {
    if (property_exists($this, $offset)) {
      return $this->{$offset};
    }
    return $this->optionsBackfill[$offset];
  }

  /**
   * This is for BC support only.
   */
  public function offsetSet(mixed $offset, mixed $value): void {
    if (property_exists($this, $offset)) {
      $this->{$offset} = $value;
      return;
    }
    $this->optionsBackfill[$offset] = $value;
  }

  /**
   * This is for BC support only.
   */
  public function offsetUnset(mixed $offset): void {
    if (property_exists($this, $offset)) {
      $this->{$offset} = NULL;
      return;
    }
    unset($this->optionsBackfill[$offset]);
  }

}
