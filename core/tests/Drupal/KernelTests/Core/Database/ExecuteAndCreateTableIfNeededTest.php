<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Config\DatabaseStorage as ConfigDatabaseStorage;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\PrimaryKey;
use Drupal\Core\Database\SchemaDefinition\Schema;
use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionType;
use Drupal\Core\Database\SchemaDefinition\Table;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests table creation without the driver's query-level recovery savepoints.
 */
#[CoversClass(Connection::class)]
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class ExecuteAndCreateTableIfNeededTest extends DatabaseTestBase {

  /**
   * Tests lazy table creation inside and outside a transaction.
   */
  #[DataProvider('transactionProvider')]
  public function testExecution(bool $in_transaction): void {
    $transaction = $in_transaction ? $this->connection->startTransaction() : NULL;
    $attempts = 0;
    $table = 'lazy_execution_test';
    $definition = new Schema(SchemaDefinitionType::Storage, 'lazy_execution', [
      new Table($table, [Column::int('id', notNull: TRUE)], primaryKey: new PrimaryKey(['id'])),
      new Table('lazy_unrelated', [Column::int('id')]),
    ]);
    $result = $this->connection->executeAndCreateTableIfNeeded($table, $definition, function () use ($table, &$attempts): string {
      $attempts++;
      // Bypass query() so a PostgreSQL failure really aborts the transaction.
      try {
        $this->connection->getClientConnection()->exec('INSERT INTO ' . $this->connection->getFullQualifiedTableName($table) . ' (id) VALUES (1)');
      }
      catch (\PDOException $e) {
        throw new DatabaseExceptionWrapper($e->getMessage(), 0, $e);
      }
      return 'inserted';
    });
    $this->assertSame('inserted', $result);
    $this->assertFalse($this->connection->schema()->tableExists('lazy_unrelated'));
    $this->assertSame($in_transaction && $this->checksTableFirstInTransaction() ? 1 : 2, $attempts);
    $transaction?->commitOrRelease();
    $this->assertSame('1', (string) $this->connection->select($table)->countQuery()->execute()->fetchField());
  }

  /**
   * Determines whether the driver checks for the table before the operation.
   *
   * Drivers where a failed statement aborts a transaction override
   * Connection::executeAndCreateTableIfNeeded() to check first.
   */
  protected function checksTableFirstInTransaction(): bool {
    return (new \ReflectionMethod($this->connection, 'executeAndCreateTableIfNeeded'))->getDeclaringClass()->getName() !== Connection::class;
  }

  /**
   * Provides transaction contexts.
   */
  public static function transactionProvider(): array {
    return [[FALSE], [TRUE]];
  }

  /**
   * Tests recovery when another connection creates the table first.
   */
  public function testCreationRace(): void {
    $table = 'lazy_execution_race';
    $info = Database::getConnectionInfo()['default'];
    Database::addConnectionInfo('lazy_race', 'default', $info);
    $other = Database::getConnection('default', 'lazy_race');
    $original_schema = $this->connection->schema();
    $schema = $this->getMockBuilder($original_schema::class)
      ->setConstructorArgs([$this->connection])
      ->onlyMethods(['createTable'])
      ->getMock();
    $definition = [
      'fields' => ['id' => ['type' => 'int', 'not null' => TRUE]],
      'primary key' => ['id'],
    ];
    $schema->expects($this->once())->method('createTable')->with($table, $definition)->willReturnCallback(function () use ($table, $other, $definition): void {
      $other->schema()->createTable($table, $definition);
      try {
        $this->connection->getClientConnection()->exec('CREATE TABLE ' . $this->connection->getFullQualifiedTableName($table) . ' (id int NOT NULL)');
      }
      catch (\PDOException $e) {
        throw new DatabaseExceptionWrapper($e->getMessage(), 0, $e);
      }
    });
    $property = new \ReflectionProperty($this->connection, 'schema');
    $property->setValue($this->connection, $schema);
    try {
      $result = $this->connection->executeAndCreateTableIfNeeded($table, $definition,
        fn() => $this->connection->insert($table)->fields(['id' => 1])->execute());
      $this->assertNotFalse($result);
      $this->assertSame('1', (string) $this->connection->select($table)->countQuery()->execute()->fetchField());
    }
    finally {
      $property->setValue($this->connection, $original_schema);
      Database::removeConnection('lazy_race');
    }
  }

  /**
   * Tests the empty-result contract without unnecessary creation on safe reads.
   */
  #[DataProvider('transactionProvider')]
  public function testMissingTableRead(bool $in_transaction): void {
    $table = 'lazy_read_test';
    $storage = new ConfigDatabaseStorage($this->connection, $table);
    $transaction = $in_transaction ? $this->connection->startTransaction() : NULL;
    $this->assertFalse($storage->read('missing'));
    $this->assertSame($in_transaction && $this->checksTableFirstInTransaction(), $this->connection->schema()->tableExists($table));
    $transaction?->commitOrRelease();
  }

  /**
   * Tests that empty requests never create a table, even in a transaction.
   */
  public function testEmptyRead(): void {
    $table = 'lazy_empty_read_test';
    $storage = new ConfigDatabaseStorage($this->connection, $table);
    $transaction = $this->connection->startTransaction();
    $this->assertSame([], $storage->readMultiple([]));
    $this->assertFalse($this->connection->schema()->tableExists($table));
    $transaction->commitOrRelease();
  }

}
