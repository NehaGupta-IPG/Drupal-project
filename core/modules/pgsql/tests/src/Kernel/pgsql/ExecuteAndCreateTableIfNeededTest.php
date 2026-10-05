<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Kernel\pgsql;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\KernelTests\Core\Database\DriverSpecificDatabaseTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests connection-scoped lazy table creation on PostgreSQL.
 *
 * PostgreSQL does not support lazy table creation: a failed statement aborts
 * the enclosing transaction.
 */
#[CoversClass(Connection::class)]
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class ExecuteAndCreateTableIfNeededTest extends DriverSpecificDatabaseTestBase {

  /**
   * The name of the table verified by the tests.
   */
  protected const string TABLE = 'table_verify_test';

  /**
   * Returns an object that delegates table verification to the connection.
   *
   * @param \Closure|null $beforeCreate
   *   (optional) A callback that runs before the table is created.
   *
   * @return object
   *   An object exposing the verification operation.
   */
  protected function createVerifier(?\Closure $beforeCreate = NULL): object {
    return new class ($this->connection, $beforeCreate) {

      /**
       * Executes a no-op after transaction-safe table creation.
       */
      public function verifyTableInTransaction(string $table): void {
        $this->connection->executeAndCreateTableIfNeeded(
          $table,
          fn() => $this->createVerifiedTable($table),
          static fn() => NULL,
        );
      }

      public function __construct(
        protected Connection $connection,
        protected ?\Closure $beforeCreate,
      ) {}

      /**
       * Returns the schema definition of the table.
       */
      public function schemaDefinition(): array {
        return [
          'fields' => [
            'id' => ['type' => 'int', 'not null' => TRUE],
          ],
          'primary key' => ['id'],
        ];
      }

      /**
       * Creates the table through the test callback.
       */
      protected function createVerifiedTable(string $table): void {
        if ($this->beforeCreate) {
          ($this->beforeCreate)($table);
        }
        $this->connection->schema()->createTable($table, $this->schemaDefinition());
      }

    };
  }

  /**
   * Tests that the driver requires table verification.
   */
  public function testLazyTableCreationNotSupported(): void {
    $this->assertFalse($this->connection->supportsLazyTableCreation());
  }

  /**
   * Tests that a missing table is created inside a transaction.
   */
  public function testMissingTableCreatedInTransaction(): void {
    $schema = $this->connection->schema();
    $this->assertFalse($schema->tableExists(static::TABLE));

    $transaction = $this->connection->startTransaction();
    $this->createVerifier()->verifyTableInTransaction(static::TABLE);
    $this->assertTrue($schema->tableExists(static::TABLE));
    $this->connection->insert(static::TABLE)->fields(['id' => 1])->execute();
    $transaction->commitOrRelease();

    $this->assertFalse($this->connection->inTransaction());
    $this->assertSame(1, (int) $this->connection->select(static::TABLE)->countQuery()->execute()->fetchField());
  }

  /**
   * Tests that verification outside a transaction does not create the table.
   */
  public function testOutsideTransactionNotCreated(): void {
    $this->createVerifier()->verifyTableInTransaction(static::TABLE);
    $this->assertFalse($this->connection->schema()->tableExists(static::TABLE));
  }

  /**
   * Tests that a root transaction rollback invalidates verification.
   */
  public function testRootRollbackInvalidatesVerification(): void {
    $schema = $this->connection->schema();
    $verifier = $this->createVerifier();

    $transaction = $this->connection->startTransaction();
    $verifier->verifyTableInTransaction(static::TABLE);
    $this->assertTrue($schema->tableExists(static::TABLE));
    $transaction->rollBack();
    unset($transaction);
    // The table creation was part of the rolled back transaction.
    $this->assertFalse($schema->tableExists(static::TABLE));

    $transaction = $this->connection->startTransaction();
    $verifier->verifyTableInTransaction(static::TABLE);
    $this->assertTrue($schema->tableExists(static::TABLE));
    $this->connection->insert(static::TABLE)->fields(['id' => 1])->execute();
    $transaction->commitOrRelease();
    $this->assertTrue($schema->tableExists(static::TABLE));
  }

  /**
   * Tests that a savepoint rollback invalidates verification.
   */
  public function testSavepointRollbackInvalidatesVerification(): void {
    $schema = $this->connection->schema();
    $verifier = $this->createVerifier();

    $transaction = $this->connection->startTransaction();
    $savepoint = $this->connection->startTransaction();
    $verifier->verifyTableInTransaction(static::TABLE);
    $this->assertTrue($schema->tableExists(static::TABLE));
    $savepoint->rollBack();
    unset($savepoint);
    $this->assertFalse($schema->tableExists(static::TABLE));

    $verifier->verifyTableInTransaction(static::TABLE);
    $this->assertTrue($schema->tableExists(static::TABLE));
    $this->connection->insert(static::TABLE)->fields(['id' => 1])->execute();
    $transaction->commitOrRelease();
    $this->assertSame(1, (int) $this->connection->select(static::TABLE)->countQuery()->execute()->fetchField());
  }

  /**
   * Tests that a released savepoint keeps the verification.
   */
  public function testSavepointReleaseKeepsVerification(): void {
    $verifier = $this->createVerifier();
    $transaction = $this->connection->startTransaction();
    $savepoint = $this->connection->startTransaction();
    $verifier->verifyTableInTransaction(static::TABLE);
    $savepoint->commitOrRelease();
    unset($savepoint);

    // Drop the table behind the back of the schema object, so a new
    // verification would create it again.
    $this->connection->query('DROP TABLE {' . static::TABLE . '}');
    $verifier->verifyTableInTransaction(static::TABLE);
    $this->assertFalse($this->connection->schema()->tableExists(static::TABLE));
    $transaction->rollBack();
  }

  /**
   * Tests that dropping the table invalidates verification.
   */
  public function testDropTableInvalidatesVerification(): void {
    $schema = $this->connection->schema();
    $verifier = $this->createVerifier();

    $transaction = $this->connection->startTransaction();
    $verifier->verifyTableInTransaction(static::TABLE);
    $this->assertTrue($schema->dropTable(static::TABLE));
    $this->assertFalse($schema->tableExists(static::TABLE));

    $verifier->verifyTableInTransaction(static::TABLE);
    $this->assertTrue($schema->tableExists(static::TABLE));
    $this->connection->insert(static::TABLE)->fields(['id' => 1])->execute();
    $transaction->commitOrRelease();
  }

  /**
   * Tests that a lost table creation race keeps the transaction usable.
   *
   * Another connection creates the table between the existence check and the
   * creation. The failed CREATE TABLE statement aborts the savepoint only.
   */
  public function testLostCreationRaceKeepsTransactionUsable(): void {
    Database::addConnectionInfo('table_verify_race', 'default', Database::getConnectionInfo()['default']);
    $other = Database::getConnection('default', 'table_verify_race');

    $verifier = $this->createVerifier(function (string $table) use ($other): void {
      $other->schema()->createTable($table, [
        'fields' => [
          'id' => ['type' => 'int', 'not null' => TRUE],
        ],
        'primary key' => ['id'],
      ]);
      // Run the statement on the client connection. This skips the existence
      // check in Schema::createTable() and the savepoint that
      // Connection::query() adds, so the failure aborts the transaction like
      // it does when the race is lost.
      try {
        $this->connection->getClientConnection()->exec('CREATE TABLE ' . $this->connection->getFullQualifiedTableName($table) . ' (id int NOT NULL)');
      }
      catch (\PDOException $e) {
        throw new DatabaseExceptionWrapper($e->getMessage(), 0, $e);
      }
    });

    $transaction = $this->connection->startTransaction();
    $verifier->verifyTableInTransaction(static::TABLE);
    $this->assertTrue($this->connection->inTransaction());
    $this->connection->insert(static::TABLE)->fields(['id' => 1])->execute();
    $transaction->commitOrRelease();

    $this->assertSame(1, (int) $this->connection->select(static::TABLE)->countQuery()->execute()->fetchField());
    Database::removeConnection('table_verify_race');
  }

  /**
   * Tests that services sharing a connection also share verification.
   */
  public function testSharedVerification(): void {
    $transaction = $this->connection->startTransaction();
    $this->createVerifier()->verifyTableInTransaction(static::TABLE);
    $this->connection->query('DROP TABLE {' . static::TABLE . '}');
    // A second service must reuse the connection's verification rather than
    // consulting the schema again. Raw DDL intentionally bypasses invalidation.
    $this->createVerifier()->verifyTableInTransaction(static::TABLE);
    $this->assertFalse($this->connection->schema()->tableExists(static::TABLE));
    $transaction->rollBack();
  }

  /**
   * Tests that a real creation error propagates and preserves the transaction.
   */
  public function testCreationFailurePreservesTransaction(): void {
    $transaction = $this->connection->startTransaction();
    $error = new DatabaseExceptionWrapper('Creation failed');
    try {
      $this->connection->executeAndCreateTableIfNeeded(static::TABLE, function () use ($error): void {
        try {
          $this->connection->getClientConnection()->exec('CREATE TABLE ' . $this->connection->getFullQualifiedTableName(static::TABLE) . ' (id nonexistent_data_type)');
        }
        catch (\PDOException) {
          throw $error;
        }
      }, function (): void {
        $this->fail('The operation must not run after a creation failure.');
      });
      $this->fail('The creation error must propagate.');
    }
    catch (DatabaseExceptionWrapper $caught) {
      $this->assertSame($error, $caught);
    }
    $this->assertSame('1', (string) $this->connection->getClientConnection()->query('SELECT 1')->fetchColumn());
    $this->assertFalse($this->connection->schema()->tableExists(static::TABLE));
    $transaction->commitOrRelease();
  }

}
