<?php

declare(strict_types=1);

namespace Drupal\Tests\sqlite\Kernel\sqlite;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\Exception\SchemaTableAlreadyExistsException;
use Drupal\KernelTests\Core\Database\DriverSpecificKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Tests the classification of SQLite specific table errors.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class TableExceptionHandlerTest extends DriverSpecificKernelTestBase {

  /**
   * Tests SQLite messages that need specific handling.
   */
  #[TestWith([
    'ALTER TABLE {normalization_test} RENAME TO normalization_target',
    SchemaTableAlreadyExistsException::class,
    'already another table or index with this name: normalization_target',
  ])]
  #[TestWith([
    'CREATE INDEX {normalization_index} ON normalization_test (id)',
    DatabaseExceptionWrapper::class,
    'index normalization_index already exists',
  ])]
  public function testNativeErrors(string $sql, string $expected, string $expected_message): void {
    $this->connection->query('CREATE TABLE {normalization_test} (id INTEGER)');
    $this->connection->query('CREATE TABLE {normalization_target} (id INTEGER)');
    $this->connection->query('CREATE INDEX {normalization_index} ON normalization_test (id)');
    try {
      $this->connection->query($sql);
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

}
