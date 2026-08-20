<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Kernel\pgsql;

use Drupal\KernelTests\Core\Database\DriverSpecificDatabaseTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the PostgreSQL implementation of truncate queries.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class TruncateTest extends DriverSpecificDatabaseTestBase {

  /**
   * Tests that the SQL is a TRUNCATE statement, also inside a transaction.
   *
   * TRUNCATE is transactional on PostgreSQL, so unlike the parent
   * implementation there is no fallback to a DELETE statement when the
   * query runs inside a transaction.
   */
  public function testTruncateSqlStatement(): void {
    $this->assertFalse($this->connection->inTransaction());
    $this->assertStringContainsString('TRUNCATE {test}', (string) $this->connection->truncate('test'));

    $transaction = $this->connection->startTransaction();
    $this->assertTrue($this->connection->inTransaction());
    $sql = (string) $this->connection->truncate('test');
    $this->assertStringContainsString('TRUNCATE {test}', $sql);
    $this->assertStringNotContainsString('DELETE', $sql);
    $transaction->rollBack();
  }

  /**
   * Tests that the reset_serial_id option reaches the SQL statement.
   */
  public function testTruncateResetSerialIdSqlStatement(): void {
    $this->assertStringNotContainsString('RESTART IDENTITY', (string) $this->connection->truncate('test'));
    $this->assertStringContainsString('TRUNCATE {test} RESTART IDENTITY', (string) $this->connection->truncate('test', ['reset_serial_id' => TRUE]));
  }

  /**
   * Tests that the reset_serial_id option set to FALSE keeps TRUNCATE.
   *
   * TRUNCATE leaves the sequences alone on its own, so leaving the serial ID
   * alone does not cost the slower DELETE here.
   */
  public function testTruncateWithoutResetSerialIdSqlStatement(): void {
    $sql = (string) $this->connection->truncate('test', ['reset_serial_id' => FALSE]);
    $this->assertStringContainsString('TRUNCATE {test}', $sql);
    $this->assertStringNotContainsString('RESTART IDENTITY', $sql);
    $this->assertStringNotContainsString('DELETE', $sql);
  }

}
