<?php

declare(strict_types=1);

namespace Drupal\Tests\mysql\Kernel\mysql;

use Drupal\KernelTests\Core\Database\DriverSpecificDatabaseTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the MySQL implementation of truncate queries.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class TruncateTest extends DriverSpecificDatabaseTestBase {

  /**
   * Tests that the serial ID is left alone inside a transaction.
   *
   * TRUNCATE resets AUTO_INCREMENT, and both it and ALTER TABLE end a
   * transaction with an implicit COMMIT. Emptying a table inside a transaction
   * therefore keeps the DELETE that leaves AUTO_INCREMENT behind.
   */
  public function testTruncateInTransactionKeepsSerialId(): void {
    $this->assertStringContainsString('TRUNCATE {test}', (string) $this->connection->truncate('test'));

    $transaction = $this->connection->startTransaction();
    $sql = (string) $this->connection->truncate('test');
    $this->assertStringContainsString('DELETE FROM {test}', $sql);
    $this->assertStringNotContainsString('TRUNCATE', $sql);

    $last_id = (int) $this->connection->query('SELECT MAX([id]) FROM {test}')->fetchField();
    $this->connection->truncate('test')->execute();
    $new_id = (int) $this->connection->insert('test')
      ->fields(['name' => 'Freddie', 'age' => 45, 'job' => 'Great singer'])
      ->execute();
    $this->assertSame($last_id + 1, $new_id);

    $transaction->rollBack();
  }

  /**
   * Tests that the reset_serial_id option set to TRUE keeps TRUNCATE.
   *
   * TRUNCATE is what resets AUTO_INCREMENT, so asking for the reset inside a
   * transaction brings the implicit COMMIT with it.
   */
  public function testTruncateResetSerialIdSqlStatementInTransaction(): void {
    $transaction = $this->connection->startTransaction();
    $this->assertStringContainsString('DELETE FROM {test}', (string) $this->connection->truncate('test'));

    $sql = (string) $this->connection->truncate('test', ['reset_serial_id' => TRUE]);
    $this->assertStringContainsString('TRUNCATE {test}', $sql);
    $this->assertStringNotContainsString('DELETE', $sql);

    $transaction->rollBack();
  }

  /**
   * Tests that the reset_serial_id option set to FALSE keeps DELETE.
   *
   * TRUNCATE resets AUTO_INCREMENT here, so leaving the serial ID alone costs
   * a DELETE even with no transaction to fall back for.
   */
  public function testTruncateWithoutResetSerialIdSqlStatement(): void {
    $this->assertFalse($this->connection->inTransaction());

    $sql = (string) $this->connection->truncate('test', ['reset_serial_id' => FALSE]);
    $this->assertStringContainsString('DELETE FROM {test}', $sql);
    $this->assertStringNotContainsString('TRUNCATE', $sql);
  }

}
