<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Unit;

use Drupal\Core\Database\StatementInterface;
use Drupal\pgsql\Driver\Database\pgsql\Connection;
use Drupal\pgsql\Driver\Database\pgsql\TableInformationLoader;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

// cspell:ignore regclass

/**
 * Tests Drupal\pgsql\Driver\Database\pgsql\TableInformationLoader.
 */
#[CoversClass(TableInformationLoader::class)]
#[Group('Database')]
class TableInformationLoaderTest extends UnitTestCase {

  /**
   * Tests loading without a container, when no cache is available.
   */
  public function testLoadAllWithoutContainer(): void {
    $this->assertFalse(\Drupal::hasContainer());

    $connection = $this->createMock(Connection::class);
    $connection->method('getConnectionOptions')->willReturn([]);
    $connection->method('getPrefix')->willReturn('test_');
    $connection->method('escapeLike')->willReturnArgument(0);
    // Without a container the information is collected from the catalog, a
    // single time even over repeated loads.
    $connection->expects($this->once())
      ->method('query')
      ->with(
        $this->stringContains('FROM pg_class'),
        [':schema' => 'public', ':pattern' => 'test_%'],
      )
      ->willReturn($this->createStub(StatementInterface::class));

    $loader = new TableInformationLoader($connection);
    $loader->loadAll();
    $loader->loadAll();
    $this->assertNull($loader->get('public.test_unknown'));
  }

  /**
   * Tests that a temporary table is introspected individually.
   */
  public function testTemporaryTableIntrospection(): void {
    $connection = $this->createMock(Connection::class);
    $connection->method('getConnectionOptions')->willReturn([]);
    $connection->method('getPrefix')->willReturn('test_');
    // A temporary table is resolved through the pg_temp alias, and the bulk
    // load of the connection's schema is skipped.
    $connection->expects($this->once())
      ->method('query')
      ->with(
        $this->stringContains('to_regclass'),
        [':key' => '"pg_temp"."test_db_temporary_0abc"'],
      )
      ->willReturn($this->createStub(StatementInterface::class));

    $loader = new TableInformationLoader($connection);
    $info = $loader->getTableInformation('db_temporary_0abc');
    $this->assertSame([], $info->blob_fields);
    $this->assertSame([], $info->sequences);
  }

}
