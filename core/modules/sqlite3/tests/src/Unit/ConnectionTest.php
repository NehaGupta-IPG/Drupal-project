<?php

declare(strict_types=1);

namespace Drupal\Tests\sqlite3\Unit;

use Drupal\sqlite3\Driver\Database\sqlite3\Connection;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\sqlite3\Driver\Database\sqlite3\Connection
 * @group Database
 */
class ConnectionTest extends UnitTestCase {

  /**
   * @covers ::createConnectionOptionsFromUrl
   * @dataProvider providerCreateConnectionOptionsFromUrl
   *
   * @param string $url
   *   SQLite URL.
   * @param string $expected
   *   Expected connection option.
   */
  public function testCreateConnectionOptionsFromUrl(string $url, string $expected): void {
    $sqlite_connection = new Connection($this->createMock(\SQLite3::class), []);
    $database = $sqlite_connection->createConnectionOptionsFromUrl($url, NULL);
    $this->assertEquals('sqlite3', $database['driver']);
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
      'sqlite relative path' => ['sqlite3://localhost/tmp/test', 'tmp/test'],
      'sqlite absolute path' => ['sqlite3://localhost//tmp/test', '/tmp/test'],
      'in memory sqlite path' => ['sqlite3://localhost/:memory:', ':memory:'],
    ];
  }

}
