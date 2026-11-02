<?php

declare(strict_types=1);

namespace Drupal\sqlite\Driver\Database\sqlite;

use Drupal\Core\Database\ConnectionParametersBase;

/**
 * Connection parameters object for an SQLite database.
 */
class ConnectionParameters extends ConnectionParametersBase {

  public function __construct(
    public string $driver,
    public string $namespace,
    #[\SensitiveParameter] public string $autoload,
    #[\SensitiveParameter] public string $database,
    #[\SensitiveParameter] public string $prefix,
    public string $module,
    #[\SensitiveParameter] array $unknownOptions,
    #[\SensitiveParameter] public readonly array $init_commands = [],
    #[\SensitiveParameter] public readonly array $pdo = [],
  ) {
    parent::__construct($unknownOptions);
  }

  /**
   * {@inheritdoc}
   */
  public static function parseUrlPartsIntoOptions(array $urlComponents, array $queryComponents): array {
    $options = parent::parseUrlPartsIntoOptions($urlComponents, $queryComponents);
    unset(
      $options['username'],
      $options['password'],
      $options['host'],
      $options['port'],
    );
    return $options;
  }

}
