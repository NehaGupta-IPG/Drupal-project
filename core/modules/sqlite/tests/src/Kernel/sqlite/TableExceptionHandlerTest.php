<?php

declare(strict_types=1);

namespace Drupal\Tests\sqlite\Kernel\sqlite;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\Exception\SchemaTableAlreadyExistsException;
use Drupal\KernelTests\Core\Database\DriverSpecificKernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the classification of SQLite specific table errors.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class TableExceptionHandlerTest extends DriverSpecificKernelTestBase {

  /**
   * Tests SQLite messages that need specific handling.
   */
  #[DataProvider('errorProvider')]
  public function testNativeErrors(string $sql, string $expected, string $expected_message): void {
    $this->connection->query('CREATE TABLE {normalization_test} (id INTEGER)');
    $this->connection->query('CREATE TABLE {normalization_target} (id INTEGER)');
    $this->connection->query('CREATE INDEX {normalization_index} ON normalization_test (id)');
    $this->connection->query('CREATE TRIGGER {normalization_trigger} AFTER INSERT ON normalization_test BEGIN SELECT 1; END', [], ['allow_delimiter_in_query' => TRUE]);
    try {
      $this->connection->query($sql, [], ['allow_delimiter_in_query' => TRUE]);
      $this->fail('The native query must fail.');
    }
    catch (DatabaseExceptionWrapper $actual) {
      $this->assertSame($expected, $actual::class);
      $this->assertInstanceOf(\PDOException::class, $actual->getPrevious());
      $this->assertSame(1, $actual->getPrevious()->errorInfo[1]);
      $this->assertStringContainsString($expected_message, $actual->getPrevious()->getMessage());
      $this->assertStringStartsWith($actual->getPrevious()->getMessage(), $actual->getMessage());
      $this->assertNotNull($actual->info);
    }
  }

  /**
   * Provides queries producing real native errors.
   */
  public static function errorProvider(): array {
    return [
      // SQLite reports this message instead of "table ... already exists". The
      // target is unqualified because SQLite table prefixes are schema names.
      [
        'ALTER TABLE {normalization_test} RENAME TO normalization_target',
        SchemaTableAlreadyExistsException::class,
        'already another table or index with this name: normalization_target',
      ],
      [
        'CREATE INDEX {normalization_index} ON normalization_test (id)',
        DatabaseExceptionWrapper::class,
        'index normalization_index already exists',
      ],
      [
        'CREATE TRIGGER {normalization_trigger} AFTER INSERT ON normalization_test BEGIN SELECT 1; END',
        DatabaseExceptionWrapper::class,
        'trigger "normalization_trigger" already exists',
      ],
    ];
  }

}
