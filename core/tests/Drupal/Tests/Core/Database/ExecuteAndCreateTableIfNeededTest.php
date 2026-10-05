<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\Exception\SchemaDefinitionException;
use Drupal\Core\Database\Schema;
use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\Schema as SchemaDefinition;
use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionType;
use Drupal\Core\Database\SchemaDefinition\Table;
use Drupal\pgsql\Driver\Database\pgsql\Connection as PgsqlConnection;
use Drupal\sqlite\Driver\Database\sqlite\Connection as SqliteConnection;
use Drupal\Tests\Core\Database\Stub\StubConnection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Tests execution with lazy table creation.
 */
#[CoversClass(Connection::class)]
#[Group('Database')]
class ExecuteAndCreateTableIfNeededTest extends UnitTestCase {

  /**
   * Tests that driver-specific errors are recognized only by that driver.
   */
  #[TestWith([StubConnection::class, '42S02', 1146, 'Missing table', TRUE, FALSE])]
  #[TestWith([StubConnection::class, '42S01', 1050, 'Table exists', FALSE, TRUE])]
  #[TestWith([StubConnection::class, 'HY000', 1, 'no such table: lazy', FALSE, FALSE])]
  #[TestWith([StubConnection::class, '42P01', 7, 'Missing table', FALSE, FALSE])]
  #[TestWith([StubConnection::class, '23505', 7, 'pg_type_typname_nsp_index', FALSE, FALSE])]
  #[TestWith([PgsqlConnection::class, '42P01', 7, 'Missing table', TRUE, FALSE])]
  #[TestWith([PgsqlConnection::class, '42P07', 7, 'Table exists', FALSE, TRUE])]
  #[TestWith([PgsqlConnection::class, '23505', 7, 'pg_type_typname_nsp_index', FALSE, TRUE])]
  #[TestWith([PgsqlConnection::class, '23505', 7, 'other_constraint', FALSE, FALSE])]
  #[TestWith([PgsqlConnection::class, '42501', 7, 'Permission denied', FALSE, FALSE])]
  #[TestWith([SqliteConnection::class, 'HY000', 1, 'no such table: lazy', TRUE, FALSE])]
  #[TestWith([SqliteConnection::class, 'HY000', 1, 'table lazy already exists', FALSE, TRUE])]
  #[TestWith([SqliteConnection::class, 'HY000', 1, 'syntax error', FALSE, FALSE])]
  #[TestWith([SqliteConnection::class, 'HY000', 8, 'attempt to write a readonly database', FALSE, FALSE])]
  public function testDriverErrors(string $class, string $state, int $code, string $message, bool $missing, bool $race): void {
    $connection = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
    $previous = new \PDOException($message);
    $previous->errorInfo = [$state, $code, $message];
    $exception = new DatabaseExceptionWrapper($message, 0, $previous);
    $this->assertSame($missing, (new \ReflectionMethod($connection, 'isMissingTableException'))->invoke($connection, $exception));
    $this->assertSame($race, (new \ReflectionMethod($connection, 'isTableCreationRace'))->invoke($connection, $exception));
  }

  /**
   * Tests that a missing table is created and the operation retried once.
   */
  #[TestWith([FALSE])]
  #[TestWith([TRUE])]
  public function testRetry(bool $use_object): void {
    $connection = $this->getMockBuilder(StubConnection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['schema', 'supportsLazyTableCreation'])
      ->getMock();
    $connection->method('supportsLazyTableCreation')->willReturn(TRUE);
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->once())->method('tableExists')->with('lazy')->willReturn(FALSE);
    $connection->expects($this->atLeastOnce())->method('schema')->willReturn($schema);
    $table = new Table('lazy', [Column::int('id')]);
    $definition = $use_object ? new SchemaDefinition(SchemaDefinitionType::Storage, 'lazy', [$table]) : $table->toArray();
    $schema->expects($this->once())->method('createTable')->with('lazy', $table->toArray());
    $attempts = 0;
    $result = $connection->executeAndCreateTableIfNeeded('lazy', $definition, function () use (&$attempts): string {
      if (++$attempts === 1) {
        throw $this->missingTableException();
      }
      return 'result';
    });
    $this->assertSame('result', $result);
    $this->assertSame(2, $attempts);
  }

  /**
   * Tests that a schema cannot create a different table than the one requested.
   */
  public function testMissingTableDefinition(): void {
    $connection = $this->getMockBuilder(StubConnection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['schema'])
      ->getMock();
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->with('lazy')->willReturn(FALSE);
    $schema->expects($this->never())->method('createTable');
    $connection->expects($this->atLeastOnce())->method('schema')->willReturn($schema);
    $definition = new SchemaDefinition(SchemaDefinitionType::Storage, 'other', [
      new Table('other', [Column::int('id')]),
    ]);
    $this->expectException(SchemaDefinitionException::class);
    $connection->executeAndCreateTableIfNeeded('lazy', $definition,
      fn() => throw $this->missingTableException());
  }

  /**
   * Tests that successful operations do not query the schema.
   */
  public function testExistingTableFastPath(): void {
    $connection = $this->getMockBuilder(StubConnection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['schema'])
      ->getMock();
    $connection->expects($this->never())->method('schema');
    $result = new \stdClass();
    $this->assertSame($result, $connection->executeAndCreateTableIfNeeded('lazy', [], static fn() => $result));
  }

  /**
   * Tests that errors on existing tables are not retried.
   */
  public function testExistingTableFailure(): void {
    $connection = $this->getMockBuilder(StubConnection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['schema'])
      ->getMock();
    $connection->expects($this->never())->method('schema');
    $exception = new DatabaseExceptionWrapper('Permission denied');
    try {
      $connection->executeAndCreateTableIfNeeded('lazy', [], static fn() => throw $exception);
      $this->fail('The exception must propagate.');
    }
    catch (DatabaseExceptionWrapper $caught) {
      $this->assertSame($exception, $caught);
    }
  }

  /**
   * Tests that application errors do not trigger table creation.
   */
  public function testApplicationFailure(): void {
    $connection = $this->getMockBuilder(StubConnection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['schema'])
      ->getMock();
    $connection->expects($this->never())->method('schema');
    $this->expectException(\LogicException::class);
    $connection->executeAndCreateTableIfNeeded('lazy', [], static fn() => throw new \LogicException('Application failure'));
  }

  /**
   * Tests that a failed retry is propagated without further creation attempts.
   */
  public function testRetryLimit(): void {
    $connection = $this->getMockBuilder(StubConnection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['schema'])
      ->getMock();
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->once())->method('tableExists')->willReturn(FALSE);
    $schema->expects($this->once())->method('createTable')->with('lazy', []);
    $connection->expects($this->exactly(2))->method('schema')->willReturn($schema);
    $attempts = 0;
    $exception = $this->missingTableException();
    try {
      $connection->executeAndCreateTableIfNeeded('lazy', [], function () use (&$attempts, $exception): void {
        $attempts++;
        throw $exception;
      });
      $this->fail('The retry must fail.');
    }
    catch (DatabaseExceptionWrapper $caught) {
      $this->assertSame($exception, $caught);
      $this->assertSame(2, $attempts);
    }
  }

  /**
   * Tests that a creation failure is not mistaken for a concurrent creation.
   */
  public function testCreationFailure(): void {
    $connection = $this->getMockBuilder(StubConnection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['schema'])
      ->getMock();
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->exactly(2))->method('tableExists')->willReturn(FALSE);
    $connection->expects($this->exactly(3))->method('schema')->willReturn($schema);
    $exception = new DatabaseExceptionWrapper('Creation denied');
    $schema->expects($this->once())->method('createTable')->with('lazy', [])->willThrowException($exception);
    try {
      $connection->executeAndCreateTableIfNeeded('lazy', [],
        fn() => throw $this->missingTableException());
      $this->fail('Creation must fail.');
    }
    catch (DatabaseExceptionWrapper $caught) {
      $this->assertSame($exception, $caught);
    }
  }

  /**
   * Tests that an unrelated creation error survives even if the table appears.
   */
  public function testCreationErrorIsNotARace(): void {
    $connection = $this->getMockBuilder(StubConnection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['schema'])
      ->getMock();
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->exactly(2))->method('tableExists')->willReturnOnConsecutiveCalls(FALSE, TRUE);
    $connection->expects($this->atLeastOnce())->method('schema')->willReturn($schema);
    $exception = new DatabaseExceptionWrapper('Permission denied while creating an index');
    $schema->expects($this->once())->method('createTable')->with('lazy', [])->willThrowException($exception);
    try {
      $connection->executeAndCreateTableIfNeeded('lazy', [],
        fn() => throw $this->missingTableException());
      $this->fail('An unrelated creation error must propagate.');
    }
    catch (DatabaseExceptionWrapper $caught) {
      $this->assertSame($exception, $caught);
    }
  }

  /**
   * Tests that unrelated database errors never trigger creation or retry.
   */
  public function testUnrelatedDatabaseFailure(): void {
    $connection = $this->getMockBuilder(StubConnection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['schema'])
      ->getMock();
    $connection->expects($this->never())->method('schema');
    $exception = new DatabaseExceptionWrapper('Permission denied');
    $this->expectExceptionObject($exception);
    $connection->executeAndCreateTableIfNeeded('lazy', [], static fn() => throw $exception);
  }

  /**
   * Returns a missing-table exception from a PDO-based driver.
   */
  private function missingTableException(): DatabaseExceptionWrapper {
    $previous = new \PDOException('Missing table');
    $previous->errorInfo = ['42S02', 1146, 'Missing table'];
    return new DatabaseExceptionWrapper($previous->getMessage(), 0, $previous);
  }

}
