<?php

declare(strict_types=1);

namespace Drupal\Tests\sqlite\Unit;

use Drupal\Core\Database\ConnectionParametersFactory;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\sqlite\Driver\Database\sqlite\ConnectionParameters;
use Drupal\sqlite\Driver\Database\sqlite\SqliteConnection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\sqlite\Driver\Database\sqlite\Connection.
 *
 * @phpstan-import-type UnprocessedConnectionInfoArray from \Drupal\Core\Database\Database
 */
#[CoversClass(Connection::class)]
#[Group('Database')]
class ConnectionTest extends UnitTestCase {

  /**
   * Tests create connection options from url.
   *
   * @param string $url
   *   SQLite URL.
   * @param string $expected
   *   Expected connection option.
   */
  #[DataProvider('providerCreateConnectionOptionsFromUrl')]
  public function testCreateConnectionOptionsFromUrl(string $url, string $expected): void {
    /** @var UnprocessedConnectionInfoArray $connectionArray */
    $connectionArray = [
      'driver' => 'sqlite',
      'database' => ':memory:',
      'foo' => 'bar',
    ];
    $connectionParameters = ConnectionParametersFactory::createFromOptionsArray($connectionArray);
    $this->assertInstanceOf(ConnectionParameters::class, $connectionParameters);
    $sqlite_connection = new Connection($this->createStub(SqliteConnection::class), $connectionParameters);
    $database = $sqlite_connection->createConnectionOptionsFromUrl($url);
    $this->assertEquals('sqlite', $database['driver']);
    $this->assertEquals($expected, $database['database']);
  }

  /**
   * Data provider for testCreateConnectionOptionsFromUrl.
   *
   * @return string[][]
   *   Associative array of arrays with the following elements:
   *   - SQLite database URL
   *   - Expected database connection option
   */
  public static function providerCreateConnectionOptionsFromUrl(): array {
    return [
      'sqlite relative path' => ['sqlite://localhost/tmp/test', 'tmp/test'],
      'sqlite absolute path' => ['sqlite://localhost//tmp/test', '/tmp/test'],
      'in memory sqlite path' => ['sqlite://localhost/:memory:', ':memory:'],
    ];
  }

  /**
   * Confirms legacy array access to the ConnectionParameters.
   */
  public function testConnectionParametersArrayAccess(): void {
    /** @var UnprocessedConnectionInfoArray $connectionArray */
    $connectionArray = [
      'driver' => 'sqlite',
      'database' => ':memory:',
      'foo' => 'bar',
    ];
    $connectionParameters = ConnectionParametersFactory::createFromOptionsArray($connectionArray);
    $this->assertInstanceOf(ConnectionParameters::class, $connectionParameters);
    $sqlite_connection = new Connection($this->createStub(SqliteConnection::class), $connectionParameters);
    $this->assertEquals('sqlite', $sqlite_connection->getConnectionOptions()->driver);
    $this->assertEquals(':memory:', $sqlite_connection->getConnectionOptions()->database);
    $this->assertEquals('sqlite', $sqlite_connection->getConnectionOptions()['driver']);
    $this->assertEquals(':memory:', $sqlite_connection->getConnectionOptions()['database']);

    $this->assertEquals('bar', $sqlite_connection->getConnectionOptions()['foo']);
    $sqlite_connection->getConnectionOptions()['foo'] = 'qux';
    $this->assertEquals('qux', $sqlite_connection->getConnectionOptions()['foo']);
  }

}
