<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

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
  public function toArray(): array {
    return get_object_vars($this);
  }

  /**
   * {@inheritdoc}
   */
  public static function processOptionsArray(array $options): array {
    return $options;
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
