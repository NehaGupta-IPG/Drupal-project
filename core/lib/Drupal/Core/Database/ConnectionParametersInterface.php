<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

/**
 * Interface for a database connection parameters object.
 */
interface ConnectionParametersInterface {

  /**
   * Returns the PHP class name of the connection.
   *
   * @return class-string
   *   The PHP class name of the connection.
   */
  public function getDriverConnectionClassName(): string;

}
