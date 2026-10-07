<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Kernel\pgsql;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\PrimaryKey;
use Drupal\Core\Database\SchemaDefinition\Schema;
use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionType;
use Drupal\Core\Database\SchemaDefinition\Table;
use Drupal\KernelTests\Core\Database\DriverSpecificDatabaseTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests lazy schema creation on PostgreSQL.
 *
 * On PostgreSQL, a failed statement aborts the enclosing transaction, so the
 * table is checked before the operation inside a transaction.
 */
#[CoversClass(Connection::class)]
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class ExecuteWithSchemaTest extends DriverSpecificDatabaseTestBase {

  /**
   * The name of the table created by the tests.
   */
  protected const string TABLE = 'table_verify_test';

  /**
   * Returns the schema definition of the table created by the tests.
   */
  protected function definition(): Schema {
    return new Schema(SchemaDefinitionType::Storage, 'table_verify', [
      new Table(static::TABLE, [Column::int('id', notNull: TRUE)], primaryKey: new PrimaryKey(['id'])),
    ]);
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

    $this->mockTableCreation(function (string $table) use ($other): void {
      $other->schema()->createSchemaFromDefinition($this->definition());
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
    $this->connection->executeWithSchema($this->definition(),
      fn() => $this->connection->insert(static::TABLE)->fields(['id' => 1])->execute());
    $this->assertTrue($this->connection->inTransaction());
    $transaction->commitOrRelease();

    $this->assertSame(1, (int) $this->connection->select(static::TABLE)->countQuery()->execute()->fetchField());
    Database::removeConnection('table_verify_race');
  }

  /**
   * Tests that a real creation error propagates and preserves the transaction.
   */
  public function testCreationFailurePreservesTransaction(): void {
    $transaction = $this->connection->startTransaction();
    $error = new DatabaseExceptionWrapper('Creation failed');
    $this->mockTableCreation(function () use ($error): void {
      try {
        $this->connection->getClientConnection()->exec('CREATE TABLE ' . $this->connection->getFullQualifiedTableName(static::TABLE) . ' (id nonexistent_data_type)');
      }
      catch (\PDOException) {
        throw $error;
      }
    });
    try {
      $this->connection->executeWithSchema($this->definition(), function (): void {
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

  /**
   * Injects a schema creation failure without replacing real transaction logic.
   */
  private function mockTableCreation(\Closure $create_table): void {
    $schema = $this->getMockBuilder($this->connection->schema()::class)
      ->setConstructorArgs([$this->connection])
      ->onlyMethods(['createTable'])
      ->getMock();
    $schema->expects($this->once())->method('createTable')
      ->with(static::TABLE, $this->definition()->getTableDefinition(static::TABLE)->toArray())
      ->willReturnCallback($create_table);
    (new \ReflectionProperty($this->connection, 'schema'))->setValue($this->connection, $schema);
  }

}
