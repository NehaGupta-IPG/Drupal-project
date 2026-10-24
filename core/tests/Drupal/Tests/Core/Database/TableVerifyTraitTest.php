<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Schema;
use Drupal\Core\Database\SchemaObjectExistsException;
use Drupal\Core\Database\TableVerifyTrait;
use Drupal\Core\Database\Transaction;
use Drupal\Core\Database\Transaction\TransactionManagerBase;
use Drupal\Core\Database\Transaction\TransactionManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the TableVerifyTrait.
 */
#[CoversTrait(TableVerifyTrait::class)]
#[Group('Database')]
class TableVerifyTraitTest extends UnitTestCase {

  /**
   * The number of savepoints started on the connection.
   */
  protected int $savepoints = 0;

  /**
   * Creates a connection stub.
   *
   * @param \Drupal\Core\Database\Schema $schema
   *   The schema returned by the connection.
   * @param \Drupal\Core\Database\Transaction\TransactionManagerInterface $manager
   *   The transaction manager returned by the connection.
   * @param bool $lazy
   *   Whether the driver supports lazy table creation.
   * @param bool $inTransaction
   *   Whether the connection is in a transaction.
   *
   * @return \Drupal\Core\Database\Connection
   *   The connection stub.
   */
  protected function createConnection(Schema $schema, TransactionManagerInterface $manager, bool $lazy, bool $inTransaction): Connection {
    $connection = $this->createStub(Connection::class);
    $connection->method('supportsLazyTableCreation')->willReturn($lazy);
    $connection->method('inTransaction')->willReturn($inTransaction);
    $connection->method('schema')->willReturn($schema);
    $connection->method('transactionManager')->willReturn($manager);
    $connection->method('startTransaction')->willReturnCallback(function () use ($connection): Transaction {
      $this->savepoints++;
      return new Transaction($connection, 'savepoint_' . $this->savepoints, 'id_' . $this->savepoints);
    });
    return $connection;
  }

  /**
   * Returns an object that uses the trait.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The connection the table lives on.
   *
   * @return object
   *   An object exposing the trait methods.
   */
  protected function createVerifier(Connection $connection): object {
    return new class ($connection) {

      use TableVerifyTrait {
        verifyTable as public;
        verifyTableInTransaction as public;
        verifyTableRequired as public;
      }

      public function __construct(protected Connection $connection) {}

      /**
       * Returns the schema definition of the table.
       */
      public function schemaDefinition(): array {
        return ['fields' => ['id' => ['type' => 'int']]];
      }

    };
  }

  /**
   * Tests that drivers with lazy table creation skip verification.
   */
  public function testLazyTableCreationSkipsVerification(): void {
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->never())->method('tableExists');
    $schema->expects($this->never())->method('createTable');
    $connection = $this->createConnection($schema, $this->createStub(TransactionManagerBase::class), TRUE, TRUE);

    $verifier = $this->createVerifier($connection);
    $this->assertFalse($verifier->verifyTableRequired());
    $verifier->verifyTableInTransaction('test_table');
    $verifier->verifyTable('test_table');
    $this->assertSame(0, $this->savepoints);
  }

  /**
   * Tests that verification is only required inside a transaction.
   */
  public function testOutsideTransactionSkipsVerification(): void {
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->never())->method('tableExists');
    $schema->expects($this->never())->method('createTable');
    $connection = $this->createConnection($schema, $this->createStub(TransactionManagerBase::class), FALSE, FALSE);

    $verifier = $this->createVerifier($connection);
    $this->assertFalse($verifier->verifyTableRequired());
    $verifier->verifyTableInTransaction('test_table');
  }

  /**
   * Tests that a missing table is created inside a savepoint.
   */
  public function testMissingTableCreatedInTransaction(): void {
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->once())->method('tableExists')->with('test_table')->willReturn(FALSE);
    $schema->expects($this->once())->method('createTable')
      ->with('test_table', ['fields' => ['id' => ['type' => 'int']]]);
    $manager = $this->createMock(TransactionManagerBase::class);
    $manager->expects($this->once())->method('unpile')->with('savepoint_1', 'id_1');
    $manager->expects($this->never())->method('rollback');
    $connection = $this->createConnection($schema, $manager, FALSE, TRUE);

    $verifier = $this->createVerifier($connection);
    $this->assertTrue($verifier->verifyTableRequired());
    $verifier->verifyTableInTransaction('test_table');
    $this->assertSame(1, $this->savepoints);
  }

  /**
   * Tests that a missing table is created without a savepoint.
   */
  public function testMissingTableCreatedOutsideTransaction(): void {
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->once())->method('tableExists')->with('test_table')->willReturn(FALSE);
    $schema->expects($this->once())->method('createTable')->with('test_table');
    $connection = $this->createConnection($schema, $this->createStub(TransactionManagerBase::class), FALSE, FALSE);

    $this->createVerifier($connection)->verifyTable('test_table');
    $this->assertSame(0, $this->savepoints);
  }

  /**
   * Tests that an existing table is not created.
   */
  public function testExistingTableNotCreated(): void {
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->once())->method('tableExists')->with('test_table')->willReturn(TRUE);
    $schema->expects($this->never())->method('createTable');
    $connection = $this->createConnection($schema, $this->createStub(TransactionManagerBase::class), FALSE, TRUE);

    $this->createVerifier($connection)->verifyTableInTransaction('test_table');
    $this->assertSame(0, $this->savepoints);
  }

  /**
   * Tests that a lost table creation race rolls back the savepoint.
   */
  public function testLostCreationRaceRollsBackSavepoint(): void {
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->willReturn(FALSE);
    $schema->expects($this->once())->method('createTable')
      ->willThrowException(new SchemaObjectExistsException("Table 'test_table' already exists."));
    $manager = $this->createMock(TransactionManagerBase::class);
    $manager->expects($this->never())->method('unpile');
    $manager->expects($this->once())->method('rollback')->with('savepoint_1', 'id_1');
    $connection = $this->createConnection($schema, $manager, FALSE, TRUE);

    $this->createVerifier($connection)->verifyTableInTransaction('test_table');
    $this->assertSame(1, $this->savepoints);
  }

  /**
   * Tests that a lost table creation race outside a transaction is ignored.
   */
  public function testLostCreationRaceOutsideTransaction(): void {
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->willReturn(FALSE);
    $schema->expects($this->once())->method('createTable')
      ->willThrowException(new SchemaObjectExistsException("Table 'test_table' already exists."));
    $connection = $this->createConnection($schema, $this->createStub(TransactionManagerBase::class), FALSE, FALSE);

    $this->createVerifier($connection)->verifyTable('test_table');
    $this->assertSame(0, $this->savepoints);
  }

  /**
   * Tests that verification is cached until a version changes.
   */
  public function testVerificationCachedPerVersion(): void {
    $manager = $this->createStub(TransactionManagerBase::class);
    $manager->method('getTransactionVersion')->willReturnOnConsecutiveCalls(0, 0, 1, 1, 1);
    $schema = $this->createMock(Schema::class);
    $schema->method('getTableInformationVersion')->willReturnOnConsecutiveCalls(0, 0, 0, 0, 1);
    // Verified on the first call, after the transaction version changed and
    // after the table information version changed.
    $schema->expects($this->exactly(3))->method('tableExists')->willReturn(TRUE);
    $connection = $this->createConnection($schema, $manager, FALSE, TRUE);

    $verifier = $this->createVerifier($connection);
    for ($i = 0; $i < 5; $i++) {
      $verifier->verifyTableInTransaction('test_table');
    }
  }

  /**
   * Tests that verification is cached per table.
   */
  public function testVerificationCachedPerTable(): void {
    $tables = [];
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->exactly(2))->method('tableExists')
      ->willReturnCallback(function (string $table) use (&$tables): bool {
        $tables[] = $table;
        return TRUE;
      });
    $connection = $this->createConnection($schema, $this->createStub(TransactionManagerBase::class), FALSE, TRUE);

    $verifier = $this->createVerifier($connection);
    $verifier->verifyTableInTransaction('table_a');
    $verifier->verifyTableInTransaction('table_b');
    $verifier->verifyTableInTransaction('table_a');
    $verifier->verifyTableInTransaction('table_b');
    $this->assertSame(['table_a', 'table_b'], $tables);
  }

  /**
   * Tests that verification is cached against the version before creation.
   *
   * A rollback during the table creation changes the transaction version, so
   * the next call has to verify the table again.
   */
  public function testVerificationCachedAgainstVersionBeforeCreation(): void {
    $version = 0;
    $manager = $this->createStub(TransactionManagerBase::class);
    $manager->method('getTransactionVersion')->willReturnCallback(function () use (&$version): int {
      return $version;
    });
    $manager->method('rollback')->willReturnCallback(function () use (&$version): void {
      $version++;
    });
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->exactly(2))->method('tableExists')->willReturnOnConsecutiveCalls(FALSE, TRUE);
    $schema->expects($this->once())->method('createTable')
      ->willThrowException(new SchemaObjectExistsException("Table 'test_table' already exists."));
    $connection = $this->createConnection($schema, $manager, FALSE, TRUE);

    $verifier = $this->createVerifier($connection);
    $verifier->verifyTableInTransaction('test_table');
    $verifier->verifyTableInTransaction('test_table');
    // Cached against the version after the rollback.
    $verifier->verifyTableInTransaction('test_table');
  }

  /**
   * Tests that verification is not cached without version tracking.
   */
  public function testVerificationNotCachedWithoutTransactionManagerBase(): void {
    $schema = $this->createMock(Schema::class);
    $schema->expects($this->exactly(3))->method('tableExists')->willReturn(TRUE);
    $connection = $this->createConnection($schema, $this->createStub(TransactionManagerInterface::class), FALSE, TRUE);

    $verifier = $this->createVerifier($connection);
    for ($i = 0; $i < 3; $i++) {
      $verifier->verifyTableInTransaction('test_table');
    }
  }

}
