<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\Schema;
use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\Schema as SchemaDefinition;
use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionType;
use Drupal\Core\Database\SchemaDefinition\Table;
use Drupal\mysqli\Driver\Database\mysqli\Connection as MysqliConnection;
use Drupal\pgsql\Driver\Database\pgsql\Connection as PgsqlConnection;
use Drupal\sqlite\Driver\Database\sqlite\Connection as SqliteConnection;
use Drupal\Tests\Core\Database\Stub\StubConnection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;

// cspell:ignore typname

/**
 * Tests execution with lazy schema creation.
 */
#[CoversClass(Connection::class)]
#[Group('Database')]
#[AllowMockObjectsWithoutExpectations]
class ExecuteWithSchemaTest extends UnitTestCase {

  /**
   * Tests that driver-specific errors are recognized only by that driver.
   */
  #[TestWith([StubConnection::class, '42S02', 1146, 'Missing table', TRUE, FALSE])]
  #[TestWith([StubConnection::class, '42S01', 1050, 'Table exists', FALSE, TRUE])]
  #[TestWith([PgsqlConnection::class, '42P01', 7, 'Missing table', TRUE, FALSE])]
  #[TestWith([PgsqlConnection::class, '42P07', 7, 'Table exists', FALSE, TRUE])]
  #[TestWith([PgsqlConnection::class, '42710', 7, 'Type exists', FALSE, TRUE])]
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
   * Tests that mysqli errors are recognized.
   */
  #[TestWith(['42S02', 1146, TRUE, FALSE])]
  #[TestWith(['42S01', 1050, FALSE, TRUE])]
  #[TestWith(['42000', 1142, FALSE, FALSE])]
  public function testMysqliErrors(string $state, int $code, bool $missing, bool $race): void {
    $connection = (new \ReflectionClass(MysqliConnection::class))->newInstanceWithoutConstructor();
    $previous = new \mysqli_sql_exception('Error', $code);
    (new \ReflectionProperty($previous, 'sqlstate'))->setValue($previous, $state);
    $exception = new DatabaseExceptionWrapper('Error', 0, $previous);
    $this->assertSame($missing, (new \ReflectionMethod($connection, 'isMissingTableException'))->invoke($connection, $exception));
    $this->assertSame($race, (new \ReflectionMethod($connection, 'isTableCreationRace'))->invoke($connection, $exception));
  }

  /**
   * Tests that a missing table is created and the operation retried once.
   */
  public function testRetry(): void {
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->once())->method('tableExists')->with('lazy')->willReturn(FALSE);
    $schema->expects($this->once())->method('createTable')->with('lazy', $this->table()->toArray());
    $attempts = 0;
    $result = $this->createConnection($schema)->executeWithSchema($this->definition(), function () use (&$attempts): string {
      if (++$attempts === 1) {
        throw $this->missingTableException();
      }
      return 'result';
    });
    $this->assertSame('result', $result);
    $this->assertSame(2, $attempts);
  }

  /**
   * Tests a retry without creation when another process created the table.
   */
  public function testTableCreatedAfterFailure(): void {
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->once())->method('tableExists')->with('lazy')->willReturn(TRUE);
    $schema->expects($this->never())->method('createTable');
    $attempts = 0;
    $result = $this->createConnection($schema)->executeWithSchema($this->definition(), function () use (&$attempts): string {
      if (++$attempts === 1) {
        throw $this->missingTableException();
      }
      return 'result';
    });
    $this->assertSame('result', $result);
    $this->assertSame(2, $attempts);
  }

  /**
   * Tests that losing a creation race is not an error.
   */
  public function testCreationRace(): void {
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->exactly(2))->method('tableExists')->with('lazy')->willReturnOnConsecutiveCalls(FALSE, TRUE);
    $previous = new \PDOException('Table already exists');
    $previous->errorInfo = ['42S01', 1050, 'Table already exists'];
    $schema->expects($this->once())->method('createTable')->willThrowException(new DatabaseExceptionWrapper($previous->getMessage(), 0, $previous));
    $attempts = 0;
    $result = $this->createConnection($schema)->executeWithSchema($this->definition(), function () use (&$attempts): string {
      if (++$attempts === 1) {
        throw $this->missingTableException();
      }
      return 'result';
    });
    $this->assertSame('result', $result);
  }

  /**
   * Tests that successful operations do not query the schema.
   */
  public function testExistingTableFastPath(): void {
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->never())->method('tableExists');
    $result = new \stdClass();
    $this->assertSame($result, $this->createConnection($schema)->executeWithSchema($this->definition(), static fn() => $result));
  }

  /**
   * Tests that a failed retry is propagated without further creation attempts.
   */
  public function testRetryLimit(): void {
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->once())->method('tableExists')->willReturn(FALSE);
    $schema->expects($this->once())->method('createTable');
    $attempts = 0;
    $exception = $this->missingTableException();
    try {
      $this->createConnection($schema)->executeWithSchema($this->definition(), function () use (&$attempts, $exception): void {
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
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->exactly(2))->method('tableExists')->willReturn(FALSE);
    $exception = new DatabaseExceptionWrapper('Creation denied');
    $schema->expects($this->once())->method('createTable')->willThrowException($exception);
    $this->expectExceptionObject($exception);
    $this->createConnection($schema)->executeWithSchema($this->definition(), fn() => throw $this->missingTableException());
  }

  /**
   * Tests that an unrelated creation error survives even if the table appears.
   */
  public function testCreationErrorIsNotARace(): void {
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->exactly(2))->method('tableExists')->willReturnOnConsecutiveCalls(FALSE, TRUE);
    $exception = new DatabaseExceptionWrapper('Permission denied while creating an index');
    $schema->expects($this->once())->method('createTable')->willThrowException($exception);
    $this->expectExceptionObject($exception);
    $this->createConnection($schema)->executeWithSchema($this->definition(), fn() => throw $this->missingTableException());
  }

  /**
   * Tests that unrelated database errors never trigger creation or retry.
   */
  public function testUnrelatedDatabaseFailure(): void {
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->never())->method('tableExists');
    $exception = new DatabaseExceptionWrapper('Permission denied');
    $this->expectExceptionObject($exception);
    $this->createConnection($schema)->executeWithSchema($this->definition(), static fn() => throw $exception);
  }

  /**
   * Creates a connection that uses the given schema object.
   */
  private function createConnection(Schema&MockObject $schema): StubConnection {
    $connection = $this->getMockBuilder(StubConnection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['schema'])
      ->getMock();
    $connection->method('schema')->willReturn($schema);
    return $connection;
  }

  /**
   * Returns the table definition used by the tests.
   */
  private function table(): Table {
    return new Table('lazy', [Column::int('id')]);
  }

  /**
   * Returns the schema definition used by the tests.
   */
  private function definition(): SchemaDefinition {
    return new SchemaDefinition(SchemaDefinitionType::Storage, 'lazy', [$this->table()]);
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
