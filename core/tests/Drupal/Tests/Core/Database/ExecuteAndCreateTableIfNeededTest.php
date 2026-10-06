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
   * Tests a retry without creation when another process created the table.
   */
  public function testTableCreatedAfterFailure(): void {
    $connection = $this->getMockBuilder(StubConnection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['schema'])
      ->getMock();
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->once())->method('tableExists')->with('lazy')->willReturn(TRUE);
    $schema->expects($this->never())->method('createTable');
    $connection->expects($this->once())->method('schema')->willReturn($schema);
    $attempts = 0;
    $result = $connection->executeAndCreateTableIfNeeded('lazy', [], function () use (&$attempts): string {
      if (++$attempts === 1) {
        throw $this->missingTableException();
      }
      return 'result';
    });
    $this->assertSame('result', $result);
    $this->assertSame(2, $attempts);
  }

  /**
   * Tests that a missing table is created and the operation retried once.
   */
  #[TestWith([FALSE])]
  #[TestWith([TRUE])]
  public function testRetry(bool $use_object): void {
    $connection = $this->getMockBuilder(StubConnection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['schema'])
      ->getMock();
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
   * Tests that an existing table is not created again.
   */
  public function testCreateTableIfNotExistsExisting(): void {
    $connection = $this->getMockBuilder(StubConnection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['schema'])
      ->getMock();
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->once())->method('tableExists')->with('lazy')->willReturn(TRUE);
    $schema->expects($this->never())->method('createTable');
    $connection->expects($this->once())->method('schema')->willReturn($schema);
    $connection->createTableIfNotExists('lazy', []);
  }

  /**
   * Tests that a missing table is created.
   */
  #[TestWith([FALSE])]
  #[TestWith([TRUE])]
  public function testCreateTableIfNotExistsMissing(bool $use_object): void {
    $connection = $this->getMockBuilder(StubConnection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['schema'])
      ->getMock();
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->once())->method('tableExists')->with('lazy')->willReturn(FALSE);
    $connection->expects($this->atLeastOnce())->method('schema')->willReturn($schema);
    $table = new Table('lazy', [Column::int('id')]);
    $definition = $use_object ? new SchemaDefinition(SchemaDefinitionType::Storage, 'lazy', [$table]) : $table->toArray();
    $schema->expects($this->once())->method('createTable')->with('lazy', $table->toArray());
    $connection->createTableIfNotExists('lazy', $definition);
  }

  /**
   * Tests that losing a creation race is not an error.
   */
  public function testCreateTableIfNotExistsRace(): void {
    $connection = $this->getMockBuilder(StubConnection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['schema'])
      ->getMock();
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->exactly(2))->method('tableExists')->with('lazy')->willReturnOnConsecutiveCalls(FALSE, TRUE);
    $connection->expects($this->exactly(3))->method('schema')->willReturn($schema);
    $previous = new \PDOException('Table already exists');
    $previous->errorInfo = ['42S01', 1050, 'Table already exists'];
    $schema->expects($this->once())->method('createTable')->with('lazy', [])->willThrowException(new DatabaseExceptionWrapper($previous->getMessage(), 0, $previous));
    $connection->createTableIfNotExists('lazy', []);
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
