<?php

declare(strict_types=1);

namespace Drupal\Tests\mysql\Unit;

use Drupal\mysql\Driver\Database\mysql\Connection;
use Drupal\mysql\Driver\Database\mysql\Upsert;
use Drupal\Tests\UnitTestCase;
use Pdo\Mysql;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the MySQL implementation of the Upsert query.
 */
#[CoversClass(Upsert::class)]
#[Group('Database')]
class UpsertTest extends UnitTestCase {

  /**
   * Tests that the generated SQL uses the correct ON DUPLICATE KEY UPDATE form.
   *
   * The VALUES() function was deprecated in MySQL 8.0.20, so MySQL 8.0.19 and
   * later use a row alias instead. MariaDB and older MySQL versions keep using
   * VALUES().
   *
   * @legacy-covers ::__toString
   */
  #[DataProvider('providerUpsertSql')]
  public function testUpsertSql(string $server_version, string $expected_update): void {
    $connection = $this->getMockBuilder(Connection::class)
      ->setConstructorArgs([$this->createStub(Mysql::class), []])
      ->onlyMethods(['getServerVersion'])
      ->getMock();
    $connection->expects($this->atLeastOnce())
      ->method('getServerVersion')
      ->willReturn($server_version);

    $upsert = (new Upsert($connection, 'test_people'))
      ->key('job')
      ->fields(['job', 'age', 'name'])
      ->values([
        'job' => 'Speaker',
        'age' => 32,
        'name' => 'Meredith',
      ]);

    $this->assertStringEndsWith(' ON DUPLICATE KEY UPDATE ' . $expected_update, (string) $upsert);
  }

  /**
   * Provides test data for ::testUpsertSql().
   *
   * @return array
   *   An array of test data, each with a server version string and the
   *   expected ON DUPLICATE KEY UPDATE assignments.
   */
  public static function providerUpsertSql(): array {
    return [
      // MySQL 8.0.19 and later use a row alias.
      'MySQL 8.0.19' => [
        '8.0.19',
        '`age` = drupal_upsert_alias.`age`, `name` = drupal_upsert_alias.`name`',
      ],
      'MySQL 8.0.20' => [
        '8.0.20',
        '`age` = drupal_upsert_alias.`age`, `name` = drupal_upsert_alias.`name`',
      ],
      'MySQL 8.4.0' => [
        '8.4.0',
        '`age` = drupal_upsert_alias.`age`, `name` = drupal_upsert_alias.`name`',
      ],
      // MySQL before 8.0.19 keeps using VALUES().
      'MySQL 8.0.18' => [
        '8.0.18',
        '`age` = VALUES(`age`), `name` = VALUES(`name`)',
      ],
      // MariaDB keeps using VALUES() regardless of version.
      'MariaDB 10.11.0' => [
        '10.11.0-MariaDB',
        '`age` = VALUES(`age`), `name` = VALUES(`name`)',
      ],
      'MariaDB 11.4.0' => [
        '11.4.0-MariaDB',
        '`age` = VALUES(`age`), `name` = VALUES(`name`)',
      ],
    ];
  }

}
