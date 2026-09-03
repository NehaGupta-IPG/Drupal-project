<?php

declare(strict_types=1);

namespace Drupal\Tests\mysql\Kernel\mysql;

use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\Exception\SchemaTableColumnSizeTooLargeException;
use Drupal\Core\Database\Exception\SchemaTableKeyTooLargeException;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\KernelTests\Core\Database\DatabaseExceptionDumpAssertTrait;
use Drupal\KernelTests\Core\Database\DriverSpecificDatabaseTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests dumping the statement of the failures MySQL reports by error code.
 *
 * These are the kinds of failure the MySQL exception handler recognises by the
 * error code the driver reports, and turns into an exception class of its own.
 * The driver-agnostic kinds are covered once for every driver.
 *
 * @see \Drupal\mysql\Driver\Database\mysql\ExceptionHandler::rethrowNormalizedException()
 * @see \Drupal\KernelTests\Core\Database\DatabaseExceptionDumpTest
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class DatabaseExceptionDumpTest extends DriverSpecificDatabaseTestBase {

  use DatabaseExceptionDumpAssertTrait;

  /**
   * The kinds of failure MySQL reports with an error code of its own.
   *
   * @return array<string, array{0: string, 1: class-string<\Throwable>}>
   *   Each case is the name of the method provoking the failure and the
   *   exception class it is expected to throw. All of them carry SQL
   *   execution information.
   */
  public static function providerExceptionKinds(): array {
    return [
      // Error 1364, which is not an SQLSTATE 23xxx violation, but is handled
      // as one.
      'no default value' => ['missingDefault', IntegrityConstraintViolationException::class],
      // SQLSTATE 22001, error 1406.
      'data too long' => ['dataTooLong', DatabaseExceptionWrapper::class],
      // SQLSTATE 42000, error 1071.
      'key too large' => ['keyTooLarge', SchemaTableKeyTooLargeException::class],
      // SQLSTATE 42000, error 1074.
      'column too large' => ['columnSizeTooLarge', SchemaTableColumnSizeTooLargeException::class],
    ];
  }

  /**
   * Tests that every kind of failure MySQL names is reported and dumpable.
   *
   * @param string $method
   *   The method provoking the failure.
   * @param string $expected_class
   *   The exception class the failure is expected to throw.
   */
  #[DataProvider('providerExceptionKinds')]
  public function testExceptionKindIsDumpable(string $method, string $expected_class): void {
    try {
      $this->{$method}();
      $this->fail("The '$method' case did not throw.");
    }
    catch (DatabaseException $e) {
      $this->assertInstanceOf($expected_class, $e);
      $this->assertDump($e);
    }
  }

  /**
   * Tests that a failing schema change reports the statement it ran.
   *
   * A schema exception is raised from the data definition statement the schema
   * handler built, not from a query the caller wrote, so the statement is the
   * only thing that says what went wrong.
   */
  public function testSchemaStatementIsReported(): void {
    try {
      $this->columnSizeTooLarge();
      $this->fail('The schema change did not throw.');
    }
    catch (SchemaTableColumnSizeTooLargeException $e) {
      $info = $this->assertDump($e);
      $this->assertStringContainsString('CREATE TABLE', $info->sqlString);
      $this->assertStringContainsString('too_large', $info->sqlString);
      // The schema handler builds the statement itself, without placeholders.
      $this->assertSame([], $info->placeholders);
    }
  }

  /**
   * Inserts without a column that is NOT NULL and has no default value.
   */
  protected function missingDefault(): void {
    $this->connection->schema()->createTable('test_no_default', [
      'fields' => [
        'id' => ['type' => 'int', 'not null' => TRUE],
        'name' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
      ],
      'primary key' => ['id'],
    ]);
    $this->connection->insert('test_no_default')
      ->fields(['id' => 1])
      ->execute();
  }

  /**
   * Inserts a value that is longer than its column.
   */
  protected function dataTooLong(): void {
    $this->connection->insert('test')
      ->fields([
        'name' => str_repeat('too-long-', 30),
        'age' => 47,
        'job' => 'Singer',
      ])
      ->execute();
  }

  /**
   * Adds an index that is longer than the maximum key length.
   */
  protected function keyTooLarge(): void {
    $fields = [];
    foreach (range(1, 5) as $delta) {
      $fields['id' . $delta] = [
        'type' => 'varchar',
        'length' => 255,
        'not null' => TRUE,
      ];
    }
    $this->connection->schema()->createTable('test_key_too_large', [
      'fields' => ['id' => ['type' => 'int', 'not null' => TRUE]] + $fields,
      'primary key' => ['id'],
      'indexes' => ['key1' => array_keys($fields)],
    ]);
  }

  /**
   * Creates a table with a column that is too large.
   */
  protected function columnSizeTooLarge(): void {
    $this->connection->schema()->createTable('test_column_too_large', [
      'fields' => [
        'id' => ['type' => 'int', 'not null' => TRUE],
        'too_large' => ['type' => 'varchar', 'length' => 70000, 'not null' => TRUE],
      ],
      'primary key' => ['id'],
    ]);
  }

}
