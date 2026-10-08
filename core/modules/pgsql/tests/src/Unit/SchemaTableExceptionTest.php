<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Unit;

use Drupal\Core\Database\Exception\SchemaTableAlreadyExistsException;
use Drupal\Core\Database\Exception\SchemaTableDoesNotExistException;
use Drupal\Core\Database\SchemaObjectDoesNotExistException;
use Drupal\Core\Database\SchemaObjectExistsException;
use Drupal\pgsql\Driver\Database\pgsql\Connection;
use Drupal\pgsql\Driver\Database\pgsql\Schema;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests PostgreSQL schema table exception contracts.
 */
#[CoversClass(Schema::class)]
#[Group('Database')]
class SchemaTableExceptionTest extends UnitTestCase {

  /**
   * Tests table guards and compatibility with parent exception catches.
   */
  #[DataProvider('tableErrorProvider')]
  public function testTableErrors(string $method, array $exists, string $expected, string $parent, string $message): void {
    $connection = $this->createStub(Connection::class);
    $connection->method('getConnectionOptions')->willReturn([]);
    $schema = $this->getMockBuilder(Schema::class)
      ->setConstructorArgs([$connection])
      ->onlyMethods(['tableExists'])
      ->getMock();
    $schema->expects($this->exactly(count($exists)))->method('tableExists')->willReturnMap($exists);
    try {
      if ($method === 'renameTable') {
        $schema->renameTable('source', 'target');
      }
      else {
        (new \ReflectionMethod(Schema::class, $method))->invoke($schema, 'source');
      }
      $this->fail('A table exception must be thrown.');
    }
    catch (SchemaObjectDoesNotExistException | SchemaObjectExistsException $actual) {
      $this->assertSame($expected, $actual::class);
      $this->assertInstanceOf($parent, $actual);
      $this->assertSame($message, $actual->getMessage());
    }
  }

  /**
   * Provides schema table errors.
   */
  public static function tableErrorProvider(): array {
    return [
      'missing rename source' => [
        'renameTable',
        [['source', FALSE]],
        SchemaTableDoesNotExistException::class,
        SchemaObjectDoesNotExistException::class,
        "Cannot rename 'source' to 'target': table 'source' doesn't exist.",
      ],
      'existing rename target' => [
        'renameTable',
        [['source', TRUE], ['target', TRUE]],
        SchemaTableAlreadyExistsException::class,
        SchemaObjectExistsException::class,
        "Cannot rename 'source' to 'target': table 'target' already exists.",
      ],
      'missing introspected table' => [
        'introspectIndexSchema',
        [['source', FALSE]],
        SchemaTableDoesNotExistException::class,
        SchemaObjectDoesNotExistException::class,
        "The table source doesn't exist.",
      ],
    ];
  }

}
