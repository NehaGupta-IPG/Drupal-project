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
 * Tests executing an operation and creating its schema if needed.
 */
#[CoversClass(Connection::class)]
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class ExecuteAndCreateSchemaIfNeededTest extends DatabaseTestBase {

  /**
   * Tests lazy schema creation inside and outside a transaction.
   */
  #[TestWith([FALSE])]
  #[TestWith([TRUE])]
  public function testExecution(bool $in_transaction): void {
    $transaction = $in_transaction ? $this->connection->startTransaction() : NULL;
    $attempts = 0;
    $definition = new Schema(SchemaDefinitionType::Storage, 'lazy_execution', [
      new Table('lazy_execution_test', [Column::int('id', notNull: TRUE)], primaryKey: new PrimaryKey(['id'])),
      new Table('lazy_execution_other', [Column::int('id')]),
    ]);
    $result = $this->connection->executeAndCreateSchemaIfNeeded($definition, function () use (&$attempts): string {
      $attempts++;
      $this->connection->query('INSERT INTO {lazy_execution_test} ([id]) VALUES (1)');
      return 'inserted';
    });
    $this->assertSame('inserted', $result);
    $this->assertTrue($this->connection->schema()->tableExists('lazy_execution_other'));
    // Drivers where a failed statement aborts a transaction check for the
    // tables first.
    $checks_first = (new \ReflectionMethod($this->connection, 'executeAndCreateSchemaIfNeeded'))->getDeclaringClass()->getName() !== Connection::class;
    $this->assertSame($in_transaction && $checks_first ? 1 : 2, $attempts);
    $transaction?->commitOrRelease();
    $this->assertSame('1', (string) $this->connection->select('lazy_execution_test')->countQuery()->execute()->fetchField());
  }

  /**
   * Tests recovery when another connection creates the table first.
   */
  public function testCreationRace(): void {
    $table = new Table('lazy_execution_race', [Column::int('id', notNull: TRUE)], primaryKey: new PrimaryKey(['id']));
    Database::addConnectionInfo('lazy_race', 'default', Database::getConnectionInfo()['default']);
    $other = Database::getConnection('default', 'lazy_race');
    $original_schema = $this->connection->schema();
    $schema = $this->getMockBuilder($original_schema::class)
      ->setConstructorArgs([$this->connection])
      ->onlyMethods(['createTable'])
      ->getMock();
    $schema->expects($this->once())->method('createTable')->with($table->name, $table->toArray())->willReturnCallback(function () use ($table, $other): void {
      $other->schema()->createTable($table->name, $table->toArray());
      // Skip the existence check in Schema::createTable(), so the statement
      // fails like it does when the race is lost.
      $this->connection->query('CREATE TABLE {' . $table->name . '} ([id] int NOT NULL)');
    });
    $property = new \ReflectionProperty($this->connection, 'schema');
    $property->setValue($this->connection, $schema);
    try {
      $definition = new Schema(SchemaDefinitionType::Storage, 'lazy_race', [$table]);
      $result = $this->connection->executeAndCreateSchemaIfNeeded($definition,
        fn() => $this->connection->insert($table->name)->fields(['id' => 1])->execute());
      $this->assertNotFalse($result);
      $this->assertSame('1', (string) $this->connection->select($table->name)->countQuery()->execute()->fetchField());
    }
    finally {
      $property->setValue($this->connection, $original_schema);
      Database::removeConnection('lazy_race');
    }
  }

}
