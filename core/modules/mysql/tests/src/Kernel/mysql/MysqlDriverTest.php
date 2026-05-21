<?php

declare(strict_types=1);

namespace Drupal\Tests\mysql\Kernel\mysql;

use Drupal\mysql\Driver\Database\mysql\Connection;
use Drupal\KernelTests\Core\Database\DriverSpecificKernelTestBase;
use Drupal\Tests\Core\Database\Stub\StubPDO;

/**
 * Tests the deprecations of the MySQL database driver classes in Core.
 *
 * @group Database
 */
class MysqlDriverTest extends DriverSpecificKernelTestBase {

  /**
   * @covers \Drupal\mysql\Driver\Database\mysql\Connection
   */
  public function testConnection(): void {
    $connection = new Connection($this->createMock(StubPDO::class), []);
    $this->assertInstanceOf(Connection::class, $connection);
  }

  /**
   * Test a connection times out when timeout is artificially reduced.
   */
  public function testConnectionTimeout(): void {
    [$pdo, $connection] = $this->timeoutConnection();
    static::expectException(\PDOException::class);
    @$pdo->exec($connection->getDummySelectSQL());
  }

  /**
   * Test a connection can reconnect.
   */
  public function testReconnect(): void {
    [$pdo, $connection] = $this->timeoutConnection();
    $e = NULL;
    try {
      @$pdo->exec($this->connection->getDummySelectSQL());
    }
    catch (\Throwable $e) {
    }

    static::assertInstanceOf(\PDOException::class, $e);

    $connection->ping();

    // Get the new PDO object, as it was swapped out by Connection::reconnect()
    $pdo = $connection->getClientConnection();
    static::assertInstanceOf(\PDO::class, $pdo);
    static::assertEquals(0, $pdo->exec($connection->getDummySelectSQL()));
  }

  public function testDummySql(): void {
    $this->assertEquals('SELECT 1', $this->connection->getDummySelectSQL());
  }

  /**
   * Forcefully cause the active connection to timeout.
   *
   * @phpstan-return array{\PDO, \Drupal\Core\Database\Connection}
   */
  private function timeoutConnection(): array {
    $connection = $this->connection;
    $pdo = $connection->getClientConnection();
    static::assertInstanceOf(\PDO::class, $pdo);
    $timeout = 1;
    $pdo->exec(\sprintf('SET SESSION wait_timeout=%d', $timeout));
    sleep($timeout + 2);
    return [$pdo, $connection];
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Restore as KernelTestBase::tearDown tries to remove tables.
    $connection = $this->connection->getClientConnection();
    $connection->exec('SET SESSION wait_timeout=60');
    parent::tearDown();
  }

}
