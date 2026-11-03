<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\PrimaryKey;
use Drupal\Core\Database\SchemaDefinition\Schema;
use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionType;
use Drupal\Core\Database\SchemaDefinition\Table;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;

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
  #[TestWith([FALSE])]
  #[TestWith([TRUE])]
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
      $this->connection->query('INSERT INTO {' . $table . '} ([id]) VALUES (1)');
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
   * Tests table creation without an operation.
   */
  #[TestWith([FALSE])]
  #[TestWith([TRUE])]
  public function testCreateTableIfNotExists(bool $in_transaction): void {
    $transaction = $in_transaction ? $this->connection->startTransaction() : NULL;
    $table = 'lazy_create_test';
    $definition = new Schema(SchemaDefinitionType::Storage, 'lazy_create', [
      new Table($table, [Column::int('id', notNull: TRUE)], primaryKey: new PrimaryKey(['id'])),
      new Table('lazy_unrelated', [Column::int('id')]),
    ]);
    $this->connection->createTableIfNotExists($table, $definition);
    $this->assertTrue($this->connection->schema()->tableExists($table));
    $this->assertFalse($this->connection->schema()->tableExists('lazy_unrelated'));
    // A second call for an existing table is a no-op.
    $this->connection->createTableIfNotExists($table, $definition);
    $this->connection->insert($table)->fields(['id' => 1])->execute();
    $transaction?->commitOrRelease();
    $this->assertSame('1', (string) $this->connection->select($table)->countQuery()->execute()->fetchField());
  }

  /**
   * Tests recovery when another connection creates the table first.
   */
  public function testCreationRace(): void {
    $table = 'lazy_execution_race';
    $this->simulateCreationRace($table, function () use ($table): void {
      $result = $this->connection->executeAndCreateTableIfNeeded($table, $this->raceTableDefinition(),
        fn() => $this->connection->insert($table)->fields(['id' => 1])->execute());
      $this->assertNotFalse($result);
      $this->assertSame('1', (string) $this->connection->select($table)->countQuery()->execute()->fetchField());
    });
  }

  /**
   * Tests that losing a creation race does not break an enclosing transaction.
   */
  #[TestWith([FALSE])]
  #[TestWith([TRUE])]
  public function testCreateTableIfNotExistsRace(bool $in_transaction): void {
    if ($in_transaction && $this->connection->driver() === 'sqlite') {
      // SQLite starts transactions with BEGIN IMMEDIATE, so no other
      // connection can create the table while the transaction is open.
      $this->markTestSkipped('SQLite transactions hold the write lock, so a creation race cannot occur.');
    }
    $table = 'lazy_create_race';
    $this->simulateCreationRace($table, function () use ($table, $in_transaction): void {
      $transaction = $in_transaction ? $this->connection->startTransaction() : NULL;
      $this->connection->createTableIfNotExists($table, $this->raceTableDefinition());
      $this->connection->insert($table)->fields(['id' => 1])->execute();
      $transaction?->commitOrRelease();
      $this->assertSame('1', (string) $this->connection->select($table)->countQuery()->execute()->fetchField());
    });
  }

  /**
   * Returns the table definition used by the creation race tests.
   */
  protected function raceTableDefinition(): array {
    return [
      'fields' => ['id' => ['type' => 'int', 'not null' => TRUE]],
      'primary key' => ['id'],
    ];
  }

  /**
   * Runs a callback while another connection wins the table creation race.
   *
   * @param string $table
   *   The table to create.
   * @param callable $callback
   *   The callback that triggers the table creation.
   */
  protected function simulateCreationRace(string $table, callable $callback): void {
    $info = Database::getConnectionInfo()['default'];
    Database::addConnectionInfo('lazy_race', 'default', $info);
    $other = Database::getConnection('default', 'lazy_race');
    $original_schema = $this->connection->schema();
    $schema = $this->getMockBuilder($original_schema::class)
      ->setConstructorArgs([$this->connection])
      ->onlyMethods(['createTable'])
      ->getMock();
    $definition = $this->raceTableDefinition();
    $schema->expects($this->once())->method('createTable')->with($table, $definition)->willReturnCallback(function () use ($table, $other, $definition): void {
      $other->schema()->createTable($table, $definition);
      // Skip the existence check in Schema::createTable(), so the statement
      // fails like it does when the race is lost.
      $this->connection->query('CREATE TABLE {' . $table . '} ([id] int NOT NULL)');
    });
    $property = new \ReflectionProperty($this->connection, 'schema');
    $property->setValue($this->connection, $schema);
    try {
      $callback();
    }
    finally {
      $property->setValue($this->connection, $original_schema);
      Database::removeConnection('lazy_race');
    }
  }

}
