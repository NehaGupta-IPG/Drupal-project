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
   *   Expected database name.
   * @param string $expected_prefix
   *   Expected database table prefix.
   */
  #[DataProvider('providerCreateConnectionOptionsFromUrl')]
  public function testCreateConnectionOptionsFromUrl(string $url, string $expected, string $expected_prefix): void {
    $parameters = ConnectionParametersFactory::createFromUrl($url);
    $this->assertInstanceOf(ConnectionParameters::class, $parameters);
    $this->assertEquals('sqlite', $parameters->driver);
    $this->assertEquals('sqlite', $parameters->module);
    $this->assertEquals($expected, $parameters->database);
    $this->assertEquals($expected_prefix, $parameters->prefix);
  }

  /**
   * Data provider for testCreateConnectionOptionsFromUrl.
   *
   * @return string[][]
   *   Associative array of arrays with the following elements:
   *   - SQLite database URL
   *   - Expected database name
   *   - Expected database table prefix
   */
  public static function providerCreateConnectionOptionsFromUrl(): array {
    return [
      'sqlite relative path' => ['sqlite://localhost/tmp/test', 'tmp/test', ''],
      'sqlite absolute path' => ['sqlite://localhost//tmp/test', '/tmp/test', ''],
      'in memory sqlite path' => ['sqlite://localhost/:memory:', ':memory:', ''],
      'sqlite relative path with module' => ['sqlite://localhost/tmp/test?module=sqlite', 'tmp/test', ''],
      'sqlite absolute path with module' => ['sqlite://localhost//tmp/test?module=sqlite', '/tmp/test', ''],
      'in memory sqlite path with module' => ['sqlite://localhost/:memory:?module=sqlite', ':memory:', ''],
      'sqlite relative path with prefix' => ['sqlite://localhost/tmp/test#bar', 'tmp/test', 'bar'],
      'sqlite absolute path with prefix' => ['sqlite://localhost//tmp/test#bar', '/tmp/test', 'bar'],
      'in memory sqlite path with prefix' => ['sqlite://localhost/:memory:#bar', ':memory:', 'bar'],
      'sqlite relative path with module and prefix' => [
        'sqlite://localhost/tmp/test?module=sqlite#foo',
        'tmp/test',
        'foo'
      ],
      'sqlite absolute path with module and prefix' => [
        'sqlite://localhost//tmp/test?module=sqlite#foo',
        '/tmp/test',
        'foo'
      ],
      'in memory sqlite path with module and prefix' => [
        'sqlite://localhost/:memory:?module=sqlite#foo',
        ':memory:',
        'foo'
      ],
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
