<?php

declare(strict_types=1);

namespace Drupal\Tests\sqlite3\Kernel\sqlite3;

use Drupal\KernelTests\Core\Database\DriverSpecificKernelTestBase;

/**
 * Tests exceptions thrown by queries.
 *
 * @group Database
 */
class DatabaseExceptionWrapperTest extends DriverSpecificKernelTestBase {

  /**
   * Tests Connection::prepareStatement exception on execution.
   */
  public function testPrepareStatementFailOnExecution(): void {
    $this->expectException(\PDOException::class);
    $stmt = $this->connection->prepareStatement('bananas', []);
    $stmt->execute();
  }

}
