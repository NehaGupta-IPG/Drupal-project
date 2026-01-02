<?php

declare(strict_types=1);

namespace Drupal\Tests\sqlite\Kernel\sqlite;

use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\sqlite\Driver\Database\sqlite\SqliteConnection;
use Drupal\KernelTests\Core\Database\DriverSpecificKernelTestBase;
use Drupal\Tests\Core\Database\Stub\StubPDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the deprecations of the SQLite database driver.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class SqliteDriverTest extends DriverSpecificKernelTestBase {

  /**
   * Tests connection deprecation.
   */
  #[IgnoreDeprecations]
  public function testConnection(): void {
    $this->expectDeprecation("Not passing the \$eventDispatcher parameter to Drupal\\sqlite\\Driver\\Database\\sqlite\\Connection::__construct() is deprecated in drupal:11.2.0 and is throwing an error from drupal:12.0.0. See https://www.drupal.org/node/3494044");
    $connection = new Connection($this->createStub(\PHP_VERSION_ID >= 80400 ? SqliteConnection::class : StubPDO::class), []);
    $this->assertInstanceOf(Connection::class, $connection);
  }

}
