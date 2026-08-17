<?php

declare(strict_types=1);

namespace Drupal\Tests\sqlite\Kernel\sqlite;

use Drupal\Core\Database\Database;
use Drupal\KernelTests\Core\Database\DriverSpecificKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

// cSpell:ignore autoincrement

/**
 * Tests the SQLite implementation of truncate queries.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class TruncateTest extends DriverSpecificKernelTestBase {

  /**
   * Tests emptying a table of a schema that holds no serial ID.
   *
   * SQLite creates sqlite_sequence along with the first table of a schema that
   * uses AUTOINCREMENT, so the table is missing while no table of the schema
   * has a serial column.
   */
  public function testTruncateWithSerialIdWithoutSqliteSequence(): void {
    // A separate in-memory database, so that the tables of the test site do not
    // bring sqlite_sequence along. An empty prefix keeps the schema at 'main',
    // because a prefix is an attached database in this driver.
    $connection_info = Database::getConnectionInfo()['default'];
    $connection_info['database'] = ':memory:';
    unset($connection_info['prefix']);
    Database::addConnectionInfo('no_sequence', 'default', $connection_info);

    try {
      $connection = Database::getConnection('default', 'no_sequence');
      $connection->schema()->createTable('no_serial', [
        'fields' => [
          'name' => [
            'type' => 'varchar',
            'length' => 32,
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['name'],
      ]);
      $connection->insert('no_serial')->fields(['name' => 'Meredith'])->execute();

      $sequence_exists = (bool) $connection->query("SELECT 1 FROM main.sqlite_master WHERE type = :type AND name = :name", [
        ':type' => 'table',
        ':name' => 'sqlite_sequence',
      ])->fetchField();
      $this->assertFalse($sequence_exists, 'The schema holds no serial ID.');

      $connection->truncateWithSerialId('no_serial');

      $num_records_after = $connection->select('no_serial')->countQuery()->execute()->fetchField();
      $this->assertEquals(0, $num_records_after);
    }
    finally {
      Database::removeConnection('no_sequence');
    }
  }

}
