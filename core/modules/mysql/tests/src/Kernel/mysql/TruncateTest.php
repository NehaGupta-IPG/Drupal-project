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
   * Tests that the force_truncate option keeps TRUNCATE inside a transaction.
   *
   * Without the option the statement falls back to DELETE, which empties the
   * table without resetting AUTO_INCREMENT.
   */
  public function testTruncateForceTruncateSqlStatement(): void {
    $this->assertStringContainsString('TRUNCATE {test}', (string) $this->connection->truncate('test'));

    $transaction = $this->connection->startTransaction();
    $this->assertStringContainsString('DELETE FROM {test}', (string) $this->connection->truncate('test'));

    $sql = (string) $this->connection->truncate('test', ['force_truncate' => TRUE]);
    $this->assertStringContainsString('TRUNCATE {test}', $sql);
    $this->assertStringNotContainsString('DELETE', $sql);
    $transaction->rollBack();
  }

}
