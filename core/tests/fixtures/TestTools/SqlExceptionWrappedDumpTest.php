<?php

// phpcs:ignoreFile

declare(strict_types=1);

use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

// This fixture is executed by
// \Drupal\KernelTests\Core\Test\TestRunTest::testSqlStatementExceptionDumpWhenWrapped()
// and is deliberately not discoverable by the test suites.
#[RunTestsInSeparateProcesses]
class SqlExceptionWrappedDumpTest extends KernelTestBase {

  public function testWrappedSqlException(): void {
    try {
      Database::getConnection()->select('bananas_are_awesome', 'b')
        ->fields('b')
        ->condition('palm_height', 180, '>')
        ->execute();
    }
    catch (DatabaseExceptionWrapper $e) {
      // Wrapped as \Drupal\Core\Entity\Sql\SqlContentEntityStorage does it,
      // which is how most query failures surface.
      throw new EntityStorageException($e->getMessage(), $e->getCode(), $e);
    }
  }

}
