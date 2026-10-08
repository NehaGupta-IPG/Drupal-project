<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Utility\Stub;

/**
 * A stub database driver class throwing a client exception.
 *
 * Used to get a backtrace that contains an entry in the driver namespace,
 * without having to instantiate a database connection.
 */
class StubDriverStatement {

  /**
   * Throws a client exception, as a database driver statement can do.
   */
  public function execute(): void {
    throw new \PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
  }

}
