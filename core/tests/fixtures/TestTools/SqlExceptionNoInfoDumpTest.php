<?php

// phpcs:ignoreFile

declare(strict_types=1);

use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

// This fixture is executed by
// \Drupal\KernelTests\Core\Test\TestRunTest::testSqlStatementExceptionDumpWithoutInfo()
// and is deliberately not discoverable by the test suites.
#[RunTestsInSeparateProcesses]
class SqlExceptionNoInfoDumpTest extends KernelTestBase {

  public function testSqlExceptionWithoutInfo(): void {
    $specification = [
      'fields' => [
        'id' => ['type' => 'int', 'not null' => TRUE],
      ],
    ];
    $schema = Database::getConnection()->schema();
    $schema->createTable('bananas_are_awesome', $specification);
    // Creating the table again throws a database exception that is raised by
    // Drupal instead of by the database driver, so it carries no SQL
    // execution information.
    $schema->createTable('bananas_are_awesome', $specification);
  }

}
