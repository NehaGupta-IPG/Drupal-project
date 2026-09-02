<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Schema;

/**
 * Defines a Schema API index with database-specific configuration.
 */
final class Index extends \ArrayObject {

  /**
   * Constructors a schema index.
   *
   * @param array $fields
   *   Array of field specifications for the index.
   * @param array $config
   *   Array of database specific configuration, keyed by database type. Valid
   *   keys/values for the configuration are determined by the database driver.
   *
   * @see \Drupal\Core\Database\Connection::databaseType()
   */
  public function __construct(array $fields, protected array $config) {
    parent::__construct($fields);
  }

  /**
   * Gets the database specific configuration.
   *
   * @param string $database_type
   *   The database type.
   *
   * @return array
   *   Configuration; if not set, will return an empty array.
   */
  public function getDatabaseConfig(string $database_type): array {
    return $this->config[$database_type] ?? [];
  }

}
