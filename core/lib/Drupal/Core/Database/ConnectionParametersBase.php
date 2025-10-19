<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

/**
 * Base class for connection parameters objects.
 */
abstract class ConnectionParametersBase implements ConnectionParametersInterface {

  /**
   * Tables prefix.
   */
  public string $prefix = '';

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
    public readonly ?string $namespace,
    public readonly ?string $autoload,
    public readonly string $database,
    string $prefix = '',
    public readonly ?string $username = NULL,
    public readonly ?string $password = NULL,
    public readonly ?string $host = NULL,
    public readonly ?string $port = NULL,
    public readonly array $dependencies = [],
  ) {
    $this->prefix = $prefix;
  }

  /**
   * {@inheritdoc}
   */
  public function getDriverConnectionClassName(): string {
    return $this->namespace . '\\Connection';
  }

}
