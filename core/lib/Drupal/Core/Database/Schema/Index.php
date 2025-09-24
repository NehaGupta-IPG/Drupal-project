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
   *   Array of db-specific config, keyed by database type. Acceptable
   *   keys/values for the configuration are determined by the database driver.
   *
   * @see \Drupal\Core\Database\Connection::databaseType()
   */
  public function __construct(array $fields, protected array $config) {
    parent::__construct($fields);
  }

  /**
   * Getter for db-specific configuration.
   *
   * @param string $database_type
   *   Database type.
   *
   * @return array
   *   Configuration; if not set, will return an empty array.
   */
  public function getDatabaseConfig(string $database_type): array {
    return $this->config[$database_type] ?? [];
  }

}
