<?php

declare(strict_types=1);

namespace Drupal\Tests\mysql\Unit;

use Drupal\mysql\Driver\Database\mysql\Connection;
use Drupal\Tests\UnitTestCase;
use Pdo\Mysql;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;

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
  private Connection&Stub $connection;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->connection = $this->getStubBuilder(Connection::class)
      ->setConstructorArgs([$this->createStub(Mysql::class), ['driver' => 'mysql']])
      ->onlyMethods(['getServerVersion'])
      ->getStub();
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

    // ConnectionParameters is a value object, and should not be modifiable
    // with array access.
    $this->assertEquals('kaboom', $mysql_connection->getConnectionOptions()['database']);
    try {
      $mysql_connection->getConnectionOptions()['database'] = 'qux';
      $this->fail('Expected exception was not thrown');
    }
    catch (\LogicException $e) {
      $this->assertMatchesRegularExpression('/^Drupal\\\\Core\\\\Database\\\\ConnectionParametersBase.* is a value objects, writing to \'database\' is forbidden/', $e->getMessage());
    }
    $this->assertEquals('kaboom', $mysql_connection->getConnectionOptions()['database']);
    try {
      unset($mysql_connection->getConnectionOptions()['database']);
      $this->fail('Expected exception was not thrown');
    }
    catch (\LogicException $e) {
      $this->assertMatchesRegularExpression('/^Drupal\\\\Core\\\\Database\\\\ConnectionParametersBase.* is a value objects, unsetting \'database\' is forbidden/', $e->getMessage());
    }
    $this->assertEquals('kaboom', $mysql_connection->getConnectionOptions()['database']);

    // ConnectionParameters is a value object, reading/writing to non-existing
    // variables should fail.
    try {
      $this->assertEquals('warp', $mysql_connection->getConnectionOptions()['time']);
      $this->fail('Expected exception was not thrown');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertEquals('Property \'time\' does not exist', $e->getMessage());
    }
    try {
      $mysql_connection->getConnectionOptions()['time'] = 'warp';
      $this->fail('Expected exception was not thrown');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertEquals('Property \'time\' does not exist', $e->getMessage());
    }
    try {
      unset($mysql_connection->getConnectionOptions()['time']);
      $this->fail('Expected exception was not thrown');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertEquals('Property \'time\' does not exist', $e->getMessage());
    }
  }

  /**
   * Confirms cloning of ConnectionParameters.
   */
  public function testConnectionParametersCloning(): void {
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

    $newParameters = $mysql_connection->getConnectionOptions()->with([
      'database' => 'bokatan',
      'foo' => 'moff',
      'qux' => ['boba'],
    ]);
    $this->assertEquals('mysql', $newParameters->driver);
    $this->assertEquals('bokatan', $newParameters->database);
    $this->assertEquals('moff', $newParameters->foo);
    $this->assertEquals(['djarin', 'grogu'], $newParameters->din);
    $this->assertEquals(['boba'], $newParameters->qux);
  }

}
