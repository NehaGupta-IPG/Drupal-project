<?php

declare(strict_types=1);

namespace Drupal\Tests\mysql\Unit;

use Drupal\mysql\Driver\Database\mysql\Connection;
use Drupal\Tests\UnitTestCase;
use Pdo\Mysql;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

// cspell:ignore djarin grogu kuiil

/**
 * Tests MySQL database connections.
 */
#[CoversClass(Connection::class)]
#[Group('Database')]
class ConnectionTest extends UnitTestCase {

  /**
   * A mocked MySql connection.
   */
  private Connection&MockObject $connection;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->connection = $this->getMockBuilder(Connection::class)
      ->setConstructorArgs([$this->createStub(Mysql::class), []])
      ->onlyMethods(['getServerVersion'])
      ->getMock();
  }

  /**
   * Tests version and is maria db.
   *
   * @legacy-covers ::version
   * @legacy-covers ::isMariaDb
   */
  #[DataProvider('providerVersionAndIsMariaDb')]
  public function testVersionAndIsMariaDb(bool $expected_is_mariadb, string $server_version, string $expected_version): void {
    $connection = $this->getMockBuilder(Connection::class)
      ->setConstructorArgs([
        $this->createStub(Mysql::class),
        ['driver' => 'mysql', 'database' => 'foo'],
      ])
      ->onlyMethods(['getServerVersion'])
      ->getMock();

    $connection->expects($this->atLeastOnce())
      ->method('getServerVersion')
      ->willReturn($server_version);

    $is_mariadb = $connection->isMariaDb();
    $version = $connection->version();

    $this->assertSame($expected_is_mariadb, $is_mariadb);
    $this->assertSame($expected_version, $version);
  }

  /**
   * Provides test data.
   *
   * @return array
   *   An array of test data.
   */
  public static function providerVersionAndIsMariaDb(): array {
    return [
      // MariaDB.
      [
        TRUE,
        '10.2.0-MariaDB',
        '10.2.0-MariaDB',
      ],
      [
        TRUE,
        '10.2.1-MARIADB',
        '10.2.1-MARIADB',
      ],
      [
        TRUE,
        '10.2.2-alphaX-MARIADB',
        '10.2.2-alphaX-MARIADB',
      ],
      [
        TRUE,
        '5.5.5-10.2.20-MariaDB-1:10.2.20+maria~bionic',
        '10.2.20-MariaDB-1:10.2.20+maria~bionic',
      ],
      [
        TRUE,
        '5.5.5-10.3.22-MariaDB-0+deb10u1',
        '10.3.22-MariaDB-0+deb10u1',
      ],
      [
        TRUE,
        '5.5.5-10.3.22-buzz+-MariaDB-0+deb10u1',
        '10.3.22-buzz+-MariaDB-0+deb10u1',
      ],
      // MySQL.
      [
        FALSE,
        '5.5.5-10.2.20-notMariaDB',
        '5.5.5-10.2.20-notMariaDB',
      ],
      [
        FALSE,
        '5.5.5',
        '5.5.5',
      ],
      [
        FALSE,
        '5.5.5-',
        '5.5.5-',
      ],
      [
        FALSE,
        '5.7.28',
        '5.7.28',
      ],
      [
        FALSE,
        '5.7.28-31',
        '5.7.28-31',
      ],
    ];
  }

  /**
   * Confirms legacy array access to the ConnectionParameters.
   */
  public function testConnectionParametersArrayAccess(): void {
    $mysql_connection = new Connection(
      $this->createStub(Mysql::class),
      [
        'driver' => 'mysql',
        'database' => 'kaboom',
        'foo' => 'bar',
        'din' => [
          'djarin',
          'grogu',
        ],
      ],
    );
    $this->assertEquals('mysql', $mysql_connection->getConnectionOptions()->driver);
    $this->assertEquals('kaboom', $mysql_connection->getConnectionOptions()->database);
    $this->assertEquals('mysql', $mysql_connection->getConnectionOptions()['driver']);
    $this->assertEquals('kaboom', $mysql_connection->getConnectionOptions()['database']);
    $this->assertEquals('bar', $mysql_connection->getConnectionOptions()['foo']);
    $this->assertEquals(['djarin', 'grogu'], $mysql_connection->getConnectionOptions()['din']);

    $mysql_connection->getConnectionOptions()['driver'] = 'qux';
    $mysql_connection->getConnectionOptions()['database'] = 'qux';
    $mysql_connection->getConnectionOptions()['foo'] = 'qux';
    $mysql_connection->getConnectionOptions()['din']['also'] = 'kuiil';
    $mysql_connection->getConnectionOptions()['pdo'][\PDO::ATTR_EMULATE_PREPARES] = TRUE;
    $this->assertEquals('qux', $mysql_connection->getConnectionOptions()['driver']);
    $this->assertEquals('qux', $mysql_connection->getConnectionOptions()['database']);
    $this->assertEquals('qux', $mysql_connection->getConnectionOptions()['foo']);
    $this->assertEquals(['djarin', 'grogu', 'also' => 'kuiil'], $mysql_connection->getConnectionOptions()['din']);
    $this->assertEquals(['djarin', 'grogu', 'also' => 'kuiil'], $mysql_connection->getConnectionOptions()->din);
    $this->assertEquals([\PDO::ATTR_EMULATE_PREPARES => TRUE], $mysql_connection->getConnectionOptions()['pdo']);
    $this->assertEquals([\PDO::ATTR_EMULATE_PREPARES => TRUE], $mysql_connection->getConnectionOptions()->pdo);
  }

}
