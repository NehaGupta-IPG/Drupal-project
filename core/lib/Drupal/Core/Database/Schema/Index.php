<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Schema;

/**
 * Class for Schema API index specifications containing db-specific config.
 */
final class Index extends \ArrayObject {

  /**
   * Constructor.
   *
   * @param array $fields
   *   Array of field specifications for the index.
   * @param array $config
   *   Array of db-specific config, keyed by base driver module name.
   *   (The base driver being e.g. pgsql, even if the site is running a
   *   contributed driver that extends the base driver's functionality.)
   *   Acceptable keys/values for the configuration are determined by the
   *   module(s) configured.
   */
  public function __construct(array $fields, protected array $config) {
    parent::__construct($fields);
  }

  /**
   * Getter for db-specific configuration.
   *
   * @param string $driver
   *   Base driver module name.
   *
   * @return array
   *   Configuration; if not set, will return an empty array.
   */
  public function getDriverConfig(string $driver): array {
    return $this->config[$driver] ?? [];
  }

}
