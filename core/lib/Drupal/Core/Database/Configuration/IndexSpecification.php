<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Configuration;

/**
 * Class for Schema API index specifications containing driver config.
 */
final class IndexSpecification extends \ArrayObject {

  /**
   * Constructor.
   *
   * @param array $fields
   *   Array of field specifications for the index.
   * @param array $config
   *   Array of db-driver specific config, keyed by driver module name.
   *   Acceptable keys/values for the configuration are determined by the
   *   module(s) configured.
   */
  public function __construct(array $fields, protected array $config) {
    parent::__construct($fields);
  }

  /**
   * Getter for driver configuration.
   *
   * @param string $driver
   *   Driver module name.
   *
   * @return array
   *   Configuration; if not set, will return an empty array.
   */
  public function getDriverConfig(string $driver): array {
    return $this->config[$driver] ?? [];
  }

}
