<?php

declare(strict_types=1);

namespace Drupal\Tests\mysql\Kernel\mysql;

use Drupal\KernelTests\Core\Database\DriverSpecificKernelTestBase;
use Drupal\mysql\Driver\Database\mysql\Connection;
use Drupal\Tests\Core\Database\Stub\StubPDO;
use Pdo\Mysql;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the deprecations of the MySQL database driver classes in Core.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class MysqlDriverTest extends DriverSpecificKernelTestBase {

  /**
   * Tests connection.
   */
  #[IgnoreDeprecations]
  public function testConnection(): void {
    $this->expectDeprecation("Not passing the \$eventDispatcher parameter to Drupal\\mysql\\Driver\\Database\\mysql\\Connection::__construct() is deprecated in drupal:11.2.0 and is throwing an error from drupal:12.0.0. See https://www.drupal.org/node/3494044");
    // @phpstan-ignore class.notFound
    $connection = new Connection($this->createMock(\PHP_VERSION_ID >= 80400 ? Mysql::class : StubPDO::class), []);
    $this->assertInstanceOf(Connection::class, $connection);
  }

}
