<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

abstract class ConnectionParameters {

  public string $prefix = '';

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

  public function getDriverConnectionClassName(): string {
    return $this->namespace . '\\Connection';
  }

}
