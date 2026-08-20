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
   * Tests that the reset_serial_id option keeps TRUNCATE in a transaction.
   *
   * Without the option the statement falls back to DELETE, which empties the
   * table without resetting AUTO_INCREMENT.
   */
  public function testTruncateResetSerialIdSqlStatement(): void {
    $this->assertStringContainsString('TRUNCATE {test}', (string) $this->connection->truncate('test'));

    $transaction = $this->connection->startTransaction();
    $this->assertStringContainsString('DELETE FROM {test}', (string) $this->connection->truncate('test'));

    $sql = (string) $this->connection->truncate('test', ['reset_serial_id' => TRUE]);
    $this->assertStringContainsString('TRUNCATE {test}', $sql);
    $this->assertStringNotContainsString('DELETE', $sql);
    $transaction->rollBack();
  }

  /**
   * Tests that the "reset_serial_id" option set to FALSE keeps DELETE.
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
