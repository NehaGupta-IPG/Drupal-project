<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Config\DatabaseStorage as ConfigDatabaseStorage;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseExceptionWrapper;
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
    $created = 0;
    $table = 'lazy_execution_test';
    $result = $this->connection->executeAndCreateTableIfNeeded($table, function () use ($table, &$created): void {
      $created++;
      $this->connection->schema()->createTable($table, [
        'fields' => ['id' => ['type' => 'int', 'not null' => TRUE]],
        'primary key' => ['id'],
      ]);
    }, function () use ($table, &$attempts): string {
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
    $this->assertSame(1, $created);
    $this->assertSame($in_transaction && !$this->connection->supportsLazyTableCreation() ? 1 : 2, $attempts);
    $transaction?->commitOrRelease();
    $this->assertSame('1', (string) $this->connection->select($table)->countQuery()->execute()->fetchField());
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
    try {
      $result = $this->connection->executeAndCreateTableIfNeeded($table, function () use ($table, $other): void {
        $other->schema()->createTable($table, [
          'fields' => ['id' => ['type' => 'int', 'not null' => TRUE]],
          'primary key' => ['id'],
        ]);
        try {
          $this->connection->getClientConnection()->exec('CREATE TABLE ' . $this->connection->getFullQualifiedTableName($table) . ' (id int NOT NULL)');
        }
        catch (\PDOException $e) {
          throw new DatabaseExceptionWrapper($e->getMessage(), 0, $e);
        }
      }, fn() => $this->connection->insert($table)->fields(['id' => 1])->execute());
      $this->assertNotFalse($result);
      $this->assertSame('1', (string) $this->connection->select($table)->countQuery()->execute()->fetchField());
    }
    finally {
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
    $this->assertSame($in_transaction && !$this->connection->supportsLazyTableCreation(), $this->connection->schema()->tableExists($table));
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
