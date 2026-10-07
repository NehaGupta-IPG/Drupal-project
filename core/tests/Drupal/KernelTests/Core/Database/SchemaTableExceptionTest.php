<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\Exception\SchemaTableAlreadyExistsException;
use Drupal\Core\Database\Exception\SchemaTableDoesNotExistException;
use Drupal\Core\Database\SchemaObjectDoesNotExistException;
use Drupal\Core\Database\SchemaObjectExistsException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the shared schema table exception contracts.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class SchemaTableExceptionTest extends DatabaseTestBase {

  /**
   * Tests the dedicated subclasses and backward compatible parent catches.
   */
  #[DataProvider('tableErrorProvider')]
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
   * Provides schema table errors shared by the database drivers.
   */
  public static function tableErrorProvider(): array {
    return [
      ['renameTable', 'normalization_missing', SchemaTableDoesNotExistException::class],
      ['renameTable', 'test', SchemaTableAlreadyExistsException::class],
      ['introspectIndexSchema', 'normalization_missing', SchemaTableDoesNotExistException::class],
    ];
  }

}
