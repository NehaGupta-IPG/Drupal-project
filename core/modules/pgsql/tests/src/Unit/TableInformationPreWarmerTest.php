<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Unit;

use Drupal\Core\Database\Connection as CoreConnection;
use Drupal\Core\Database\StatementInterface;
use Drupal\pgsql\Driver\Database\pgsql\Connection;
use Drupal\pgsql\TableInformationPreWarmer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\pgsql\TableInformationPreWarmer.
 */
#[CoversClass(TableInformationPreWarmer::class)]
#[Group('Database')]
class TableInformationPreWarmerTest extends UnitTestCase {

  /**
   * Tests that prewarming does nothing for a connection of another driver.
   */
  public function testPreWarmRequiresPgsqlConnection(): void {
    $connection = $this->createMock(CoreConnection::class);
    $connection->expects($this->never())->method($this->anything());

    (new TableInformationPreWarmer($connection))->preWarm();
  }

  /**
   * Tests that prewarming loads the information of all tables.
   */
  public function testPreWarmLoadsAllTables(): void {
    $connection = $this->createMock(Connection::class);
    $connection->method('getConnectionOptions')->willReturn([]);
    $connection->method('getPrefix')->willReturn('test_');
    $connection->method('escapeLike')->willReturnArgument(0);
    $connection->expects($this->once())
      ->method('query')
      ->with($this->stringContains('FROM pg_class'))
      ->willReturn($this->createStub(StatementInterface::class));

    (new TableInformationPreWarmer($connection))->preWarm();
  }

}
