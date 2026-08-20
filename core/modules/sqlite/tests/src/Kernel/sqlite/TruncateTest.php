<?php

declare(strict_types=1);

namespace Drupal\Tests\sqlite\Kernel\sqlite;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseException;
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
   * The key of the connection each test method opens for itself.
   */
  private const string SEPARATE_CONNECTION_KEY = 'separate';

  /**
   * Tests emptying a table of a schema that holds no serial ID.
   *
   * SQLite creates sqlite_sequence along with the first table of a schema that
   * uses AUTOINCREMENT, so the table is missing while no table of the schema
   * has a serial column.
   */
  public function testTruncateWithSerialIdWithoutSqliteSequence(): void {
    try {
      $connection = $this->openSeparateDatabase();
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
      Database::removeConnection(self::SEPARATE_CONNECTION_KEY);
    }
  }

  /**
   * Tests that a failed delete leaves the serial ID where it was.
   *
   * Dropping the sqlite_sequence row of the table and emptying the table are
   * two statements, so a failure in the second one has the first one rolled
   * back.
   */
  public function testTruncateWithSerialIdRollsBackFailedDelete(): void {
    try {
      $connection = $this->openSeparateDatabase();
      $connection->schema()->createTable('serial', [
        'fields' => [
          'id' => [
            'type' => 'serial',
            'not null' => TRUE,
          ],
          'name' => [
            'type' => 'varchar',
            'length' => 32,
            'not null' => TRUE,
          ],
        ],
        'primary key' => ['id'],
      ]);
      $connection->insert('serial')->fields(['name' => 'Meredith'])->execute();
      $connection->insert('serial')->fields(['name' => 'Kay'])->execute();

      $mark_before = $connection->query('SELECT seq FROM main.sqlite_sequence WHERE name = :name', [':name' => 'serial'])->fetchField();
      $this->assertEquals(2, $mark_before);

      // A trigger that refuses the delete, so that emptying the table fails
      // after the sqlite_sequence row of the table has gone.
      $connection->query("CREATE TRIGGER no_delete BEFORE DELETE ON serial BEGIN SELECT RAISE(ABORT, 'no delete'); END", [], ['allow_delimiter_in_query' => TRUE]);

      try {
        $connection->truncateWithSerialId('serial');
        $this->fail('Emptying the table was expected to fail.');
      }
      catch (DatabaseException) {
        // The trigger refused the delete, which is what this test is after.
      }

      $mark_after = $connection->query('SELECT seq FROM main.sqlite_sequence WHERE name = :name', [':name' => 'serial'])->fetchField();
      $this->assertEquals($mark_before, $mark_after, 'The serial ID stayed where it was.');
      $this->assertEquals(2, $connection->select('serial')->countQuery()->execute()->fetchField());
    }
    finally {
      Database::removeConnection(self::SEPARATE_CONNECTION_KEY);
    }
  }

  /**
   * Opens a connection to a database of its own.
   *
   * The tables of the test site would bring sqlite_sequence along. An empty
   * prefix keeps the schema at 'main', because a prefix is an attached database
   * in this driver.
   *
   * @return \Drupal\Core\Database\Connection
   *   The connection.
   */
  private function openSeparateDatabase(): Connection {
    $connection_info = Database::getConnectionInfo()['default'];
    $connection_info['database'] = ':memory:';
    unset($connection_info['prefix']);
    Database::addConnectionInfo(self::SEPARATE_CONNECTION_KEY, 'default', $connection_info);
    return Database::getConnection('default', self::SEPARATE_CONNECTION_KEY);
  }

}
