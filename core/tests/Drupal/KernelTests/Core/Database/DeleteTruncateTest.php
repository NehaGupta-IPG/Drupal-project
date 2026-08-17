<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests delete and truncate queries.
 *
 * The DELETE tests are not as extensive, as all of the interesting code for
 * DELETE queries is in the conditional which is identical to the UPDATE and
 * SELECT conditional handling.
 *
 * The TRUNCATE tests are not extensive either, because the behavior of
 * TRUNCATE queries is not consistent across database engines. We only test
 * that a TRUNCATE query actually deletes all rows from the target table.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class DeleteTruncateTest extends DatabaseTestBase {

  /**
   * Confirms that we can use a subselect in a delete successfully.
   */
  public function testSubselectDelete(): void {
    $num_records_before = $this->connection->query('SELECT COUNT(*) FROM {test_task}')->fetchField();
    $pid_to_delete = $this->connection->query("SELECT * FROM {test_task} WHERE [task] = 'sleep' ORDER BY [tid]")->fetchField();

    $subquery = $this->connection->select('test', 't')
      ->fields('t', ['id'])
      ->condition('t.id', [$pid_to_delete], 'IN');
    $delete = $this->connection->delete('test_task')
      ->condition('task', 'sleep')
      ->condition('pid', $subquery, 'IN');

    $num_deleted = $delete->execute();
    $this->assertEquals(1, $num_deleted, 'Deleted 1 record.');

    $num_records_after = $this->connection->query('SELECT COUNT(*) FROM {test_task}')->fetchField();
    $this->assertEquals($num_records_before, $num_records_after + $num_deleted, 'Deletion adds up.');
  }

  /**
   * Confirms that we can delete a single record successfully.
   */
  public function testSimpleDelete(): void {
    $num_records_before = $this->connection->query('SELECT COUNT(*) FROM {test}')->fetchField();

    $num_deleted = $this->connection->delete('test')
      ->condition('id', 1)
      ->execute();
    $this->assertSame(1, $num_deleted, 'Deleted 1 record.');

    $num_records_after = $this->connection->query('SELECT COUNT(*) FROM {test}')->fetchField();
    $this->assertEquals($num_records_before, $num_records_after + $num_deleted, 'Deletion adds up.');
  }

  /**
   * Confirms that we can truncate a whole table successfully.
   */
  public function testTruncate(): void {
    $num_records_before = $this->connection->query("SELECT COUNT(*) FROM {test}")->fetchField();
    $this->assertNotEmpty($num_records_before);

    $this->connection->truncate('test')->execute();

    $num_records_after = $this->connection->query("SELECT COUNT(*) FROM {test}")->fetchField();
    $this->assertEquals(0, $num_records_after, 'Truncate really deletes everything.');
  }

  /**
   * Confirms that we can truncate a whole table while in transaction.
   */
  public function testTruncateInTransaction(): void {
    $num_records_before = $this->connection->select('test')->countQuery()->execute()->fetchField();
    $this->assertGreaterThan(0, $num_records_before, 'The table is not empty.');

    $transaction = $this->connection->startTransaction('test_truncate_in_transaction');
    $this->connection->insert('test')
      ->fields([
        'name' => 'Freddie',
        'age' => 45,
        'job' => 'Great singer',
      ])
      ->execute();
    $num_records_after_insert = $this->connection->select('test')->countQuery()->execute()->fetchField();
    $this->assertEquals($num_records_before + 1, $num_records_after_insert);

    $this->connection->truncate('test')->execute();

    // Checks that there are no records left in the table, and transaction is
    // still active.
    $this->assertTrue($this->connection->inTransaction());
    $num_records_after = $this->connection->select('test')->countQuery()->execute()->fetchField();
    $this->assertEquals(0, $num_records_after);

    // Commit the transaction, and check that there are still no records in the
    // table.
    $transaction->commitOrRelease();
    $this->assertFalse($this->connection->inTransaction());
    $num_records_after = $this->connection->select('test')->countQuery()->execute()->fetchField();
    $this->assertEquals(0, $num_records_after);
  }

  /**
   * Confirms that transaction rollback voids a truncate operation.
   */
  public function testTruncateTransactionRollback(): void {
    $num_records_before = $this->connection->select('test')->countQuery()->execute()->fetchField();
    $this->assertGreaterThan(0, $num_records_before, 'The table is not empty.');

    $transaction = $this->connection->startTransaction('test_truncate_in_transaction');
    $this->connection->insert('test')
      ->fields([
        'name' => 'Freddie',
        'age' => 45,
        'job' => 'Great singer',
      ])
      ->execute();
    $num_records_after_insert = $this->connection->select('test')->countQuery()->execute()->fetchField();
    $this->assertEquals($num_records_before + 1, $num_records_after_insert);

    $this->connection->truncate('test')->execute();

    // Checks that there are no records left in the table, and transaction is
    // still active.
    $this->assertTrue($this->connection->inTransaction());
    $num_records_after = $this->connection->select('test')->countQuery()->execute()->fetchField();
    $this->assertEquals(0, $num_records_after);

    // Roll back the transaction, and check that we are back to status before
    // insert and truncate.
    $transaction->rollBack();
    $this->assertFalse($this->connection->inTransaction());
    $num_records_after = $this->connection->select('test')->countQuery()->execute()->fetchField();
    $this->assertEquals($num_records_before, $num_records_after);
  }

  /**
   * Confirms that we can delete a single special column name record successfully.
   */
  public function testSpecialColumnDelete(): void {
    $num_records_before = $this->connection->query('SELECT COUNT(*) FROM {select}')->fetchField();

    $num_deleted = $this->connection->delete('select')
      ->condition('update', 'Update value 1')
      ->execute();
    $this->assertEquals(1, $num_deleted, 'Deleted 1 special column record.');

    $num_records_after = $this->connection->query('SELECT COUNT(*) FROM {select}')->fetchField();
    $this->assertEquals($num_records_before, $num_records_after + $num_deleted, 'Deletion adds up.');
  }

  /**
   * Deleting from a not existing table throws a DatabaseExceptionWrapper.
   */
  public function testDeleteFromNonExistingTable(): void {
    $this->expectException(DatabaseExceptionWrapper::class);
    $this->connection->delete('a-table-that-does-not-exist')->execute();
  }

  /**
   * Truncating a not existing table throws a DatabaseExceptionWrapper.
   */
  public function testTruncateNonExistingTable(): void {
    $this->expectException(DatabaseExceptionWrapper::class);
    $this->connection->truncate('a-table-that-does-not-exist')->execute();
  }

  /**
   * Confirms that emptying a table rewinds its serial ID.
   */
  public function testTruncateWithSerialId(): void {
    $num_records_before = $this->connection->select('test')->countQuery()->execute()->fetchField();
    $this->assertGreaterThan(1, $num_records_before, 'The table holds more than one row.');

    $this->connection->truncateWithSerialId('test');

    $num_records_after = $this->connection->select('test')->countQuery()->execute()->fetchField();
    $this->assertEquals(0, $num_records_after);

    // The IDs of the removed rows are forgotten, so the next row gets the
    // first one again.
    $this->assertSame(1, $this->insertPerson('Freddie'));
  }

  /**
   * Confirms that refilling leaves the serial ID at the highest ID held.
   */
  public function testTruncateWithSerialIdRefill(): void {
    $this->connection->truncateWithSerialId('test');

    // Refill with fewer rows than the table held, so that the next ID ends up
    // below where the removed rows left it.
    $this->connection->insert('test')
      ->fields(['id', 'name', 'age', 'job'])
      ->values(['id' => 1, 'name' => 'John', 'age' => 25, 'job' => 'Singer'])
      ->values(['id' => 2, 'name' => 'George', 'age' => 27, 'job' => 'Singer'])
      ->execute();

    $this->assertSame(3, $this->insertPerson('Freddie'));
  }

  /**
   * Confirms that the serial ID is rewound inside a transaction as well.
   *
   * Truncate falls back to DELETE inside a transaction, and DELETE leaves the
   * serial ID of some databases where the removed rows put it.
   */
  public function testTruncateWithSerialIdInTransaction(): void {
    $transaction = $this->connection->startTransaction('test_truncate_with_serial_id');

    $this->connection->truncateWithSerialId('test');
    $this->assertSame(1, $this->insertPerson('Freddie'));

    $transaction->commitOrRelease();
    $num_records_after = $this->connection->select('test')->countQuery()->execute()->fetchField();
    $this->assertEquals(1, $num_records_after);
  }

  /**
   * Confirms that a table without a serial ID is emptied as well.
   */
  public function testTruncateWithSerialIdWithoutSerialColumn(): void {
    // The primary key of test_people is not a serial column, so the table has
    // no serial ID to rewind.
    $num_records_before = $this->connection->select('test_people')->countQuery()->execute()->fetchField();
    $this->assertGreaterThan(0, $num_records_before, 'The table is not empty.');

    $this->connection->truncateWithSerialId('test_people');

    $num_records_after = $this->connection->select('test_people')->countQuery()->execute()->fetchField();
    $this->assertEquals(0, $num_records_after);
  }

  /**
   * Emptying a not existing table throws a DatabaseExceptionWrapper.
   */
  public function testTruncateWithSerialIdNonExistingTable(): void {
    $this->expectException(DatabaseExceptionWrapper::class);
    $this->connection->truncateWithSerialId('a-table-that-does-not-exist');
  }

  /**
   * Inserts a row into the test table without an explicit identifier.
   *
   * @param string $name
   *   The name of the person, unique within the table.
   *
   * @return int
   *   The serial ID the table handed out.
   */
  private function insertPerson(string $name): int {
    return (int) $this->connection->insert('test')
      ->fields([
        'name' => $name,
        'age' => 45,
        'job' => 'Great singer',
      ])
      ->execute();
  }

}
