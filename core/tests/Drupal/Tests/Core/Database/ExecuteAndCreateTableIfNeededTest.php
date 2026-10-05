<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\Schema;
use Drupal\Tests\Core\Database\Stub\StubConnection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests execution with lazy table creation.
 */
#[CoversClass(Connection::class)]
#[Group('Database')]
class ExecuteAndCreateTableIfNeededTest extends UnitTestCase {

  /**
   * Tests that a missing table is created and the operation retried once.
   */
  public function testRetry(): void {
    $connection = $this->getMockBuilder(StubConnection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['schema', 'supportsLazyTableCreation'])
      ->getMock();
    $connection->method('supportsLazyTableCreation')->willReturn(TRUE);
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->once())->method('tableExists')->with('lazy')->willReturn(FALSE);
    $connection->expects($this->atLeastOnce())->method('schema')->willReturn($schema);
    $created = 0;
    $attempts = 0;
    $result = $connection->executeAndCreateTableIfNeeded('lazy', function () use (&$created): void {
      $created++;
    }, function () use (&$attempts): string {
      if (++$attempts === 1) {
        throw $this->missingTableException();
      }
      return 'result';
    });
    $this->assertSame('result', $result);
    $this->assertSame(1, $created);
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
    $this->assertSame($result, $connection->executeAndCreateTableIfNeeded('lazy', function (): void {
      $this->fail('Creation is not needed.');
    }, static fn() => $result));
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
      $connection->executeAndCreateTableIfNeeded('lazy', function (): void {
        $this->fail('Creation is not needed.');
      }, static fn() => throw $exception);
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
    $connection->executeAndCreateTableIfNeeded('lazy', function (): void {
      $this->fail('Creation is not needed.');
    }, static fn() => throw new \LogicException('Application failure'));
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
    $connection->expects($this->once())->method('schema')->willReturn($schema);
    $attempts = 0;
    $created = 0;
    $exception = $this->missingTableException();
    try {
      $connection->executeAndCreateTableIfNeeded('lazy', function () use (&$created): void {
        $created++;
      }, function () use (&$attempts, $exception): void {
        $attempts++;
        throw $exception;
      });
      $this->fail('The retry must fail.');
    }
    catch (DatabaseExceptionWrapper $caught) {
      $this->assertSame($exception, $caught);
      $this->assertSame(2, $attempts);
      $this->assertSame(1, $created);
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
    $connection->expects($this->exactly(2))->method('schema')->willReturn($schema);
    $exception = new DatabaseExceptionWrapper('Creation denied');
    try {
      $connection->executeAndCreateTableIfNeeded('lazy', static fn() => throw $exception,
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
    try {
      $connection->executeAndCreateTableIfNeeded('lazy', static fn() => throw $exception,
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
    $connection->executeAndCreateTableIfNeeded('lazy', function (): void {
      $this->fail('An unrelated error must not trigger creation.');
    }, static fn() => throw $exception);
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
