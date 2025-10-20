<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

/**
 * Base class for connection parameters objects.
 */
abstract class ConnectionParametersBase implements ConnectionParametersInterface, \ArrayAccess {

  /**
   * PDO options.
   */
  public array $pdo = [];

  /**
   * Connection initialization commands.
   */
  public array $initCommands = [];

  /**
   * Tables collation.
   */
  public string $collation;

  /**
   * Transaction isolation level.
   */
  public int|string $isolationLevel;

  public function __construct(
    public readonly string $driver,
    public readonly string $namespace,
    public readonly string $autoload,
    public readonly string $database,
    public readonly string $prefix,
    public readonly ?string $username = NULL,
    public readonly ?string $password = NULL,
    public readonly ?string $host = NULL,
    public readonly ?int $port = NULL,
    public readonly array $dependencies = [],
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getDriverConnectionClassName(): string {
    return $this->namespace . '\\Connection';
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return get_object_vars($this);
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
