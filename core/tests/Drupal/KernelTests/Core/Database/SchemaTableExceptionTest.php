<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\Exception\SchemaTableAlreadyExistsException;
use Drupal\Core\Database\Exception\SchemaTableDoesNotExistException;
use Drupal\Core\Database\SchemaObjectDoesNotExistException;
use Drupal\Core\Database\SchemaObjectExistsException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Tests the shared schema table exception contracts.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class SchemaTableExceptionTest extends DatabaseTestBase {

  /**
   * Tests the dedicated subclasses and backward compatible parent catches.
   */
  #[TestWith(['renameTable', 'normalization_missing', SchemaTableDoesNotExistException::class])]
  #[TestWith(['renameTable', 'test', SchemaTableAlreadyExistsException::class])]
  #[TestWith(['introspectIndexSchema', 'normalization_missing', SchemaTableDoesNotExistException::class])]
  public function testTableErrors(string $method, string $table, string $expected): void {
    $schema = $this->connection->schema();
    try {
      if ($method === 'renameTable') {
        $schema->renameTable($table, 'test_people');
      }
      else {
        (new \ReflectionMethod($schema, $method))->invoke($schema, $table);
      }
      $this->fail('A table exception must be thrown.');
    }
    catch (SchemaObjectDoesNotExistException | SchemaObjectExistsException $actual) {
      $this->assertSame($expected, $actual::class);
      $this->assertInstanceOf($expected === SchemaTableAlreadyExistsException::class ? SchemaObjectExistsException::class : SchemaObjectDoesNotExistException::class, $actual);
      $this->assertStringContainsString($table, $actual->getMessage());
    }
  }

  /**
   * Tests that native table errors are converted by the driver.
   */
  #[TestWith(['SELECT * FROM {normalization_missing}', SchemaTableDoesNotExistException::class], 'select missing table')]
  #[TestWith(['DROP TABLE {normalization_missing}', SchemaTableDoesNotExistException::class], 'drop missing table')]
  #[TestWith(['CREATE TABLE {test} (id INTEGER)', SchemaTableAlreadyExistsException::class], 'create existing table')]
  #[TestWith(['SELECT not_a_column FROM {test}', DatabaseExceptionWrapper::class], 'unknown column')]
  public function testNativeErrors(string $sql, string $expected): void {
    try {
      $this->connection->query($sql);
      $this->fail('The query must fail.');
    }
    catch (DatabaseExceptionWrapper $actual) {
      $this->assertSame($expected, $actual::class);
      $this->assertNotNull($actual->getPrevious());
      $this->assertStringStartsWith($actual->getPrevious()->getMessage(), $actual->getMessage());
      $this->assertNotNull($actual->info);
    }
  }

}
