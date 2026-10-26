<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Kernel\pgsql;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseException;
use Drupal\KernelTests\Core\Database\DriverSpecificKernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests table verification after transactional table creation is rolled back.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class TableVerifyRollbackTest extends DriverSpecificKernelTestBase {

  /**
   * Tests reusing a cache backend after rolling back its table creation.
   */
  #[DataProvider('rollbackProvider')]
  public function testTableCreationRollback(bool $nested, bool $other_backend): void {
    $backend = $this->container->get('cache.backend.database')->get('pgsql_rollback');
    $table = 'cache_pgsql_rollback';
    $this->assertFalse($this->connection->schema()->tableExists($table));

    $outer = $this->connection->startTransaction();
    $transaction = $nested ? $this->connection->startTransaction() : $outer;
    $creator = $other_backend ? clone $backend : $backend;
    $creator->set('rolled_back', 'first');
    $this->assertSame('first', $backend->get('rolled_back')->data);
    $this->assertTrue($this->connection->schema()->tableExists($table));
    $transaction->rollBack();
    $this->assertFalse($this->connection->schema()->tableExists($table));

    if (!$nested) {
      $outer = $this->connection->startTransaction();
    }
    // Reuse the same service instance while a transaction is active. A stale
    // table verification must not let a missing-table query abort it.
    $backend->set('retained', 'second');
    $this->assertSame('second', $backend->get('retained')->data);
    $this->assertFalse($backend->get('rolled_back'));
    $this->assertEquals(1, $this->connection->query('SELECT 1')->fetchField());
    $outer->commitOrRelease();
    $this->assertSame('second', $backend->get('retained')->data);
  }

  /**
   * Tests that repeated operations reuse verification until rollback.
   */
  public function testVerificationReuse(): void {
    $backend = $this->container->get('cache.backend.database')->get('pgsql_reuse');
    $backend->set('item', 'value');
    $outer = $this->connection->startTransaction();
    $backend->get('item');

    Database::startLog('verification');
    for ($i = 0; $i < 3; $i++) {
      $this->assertSame('value', $backend->get('item')->data);
    }
    $this->assertCount(3, Database::getLog('verification'));

    $nested = $this->connection->startTransaction();
    $nested->commitOrRelease();
    Database::startLog('verification');
    $this->assertSame('value', $backend->get('item')->data);
    $this->assertCount(1, Database::getLog('verification'));

    $nested = $this->connection->startTransaction();
    $nested->rollBack();
    Database::startLog('verification');
    $this->assertSame('value', $backend->get('item')->data);
    $this->assertSame('value', $backend->get('item')->data);
    $this->assertCount(3, Database::getLog('verification'));
    $outer->commitOrRelease();
  }

  /**
   * Tests invalidation when committing an aborted PostgreSQL transaction.
   */
  public function testAbortedCommit(): void {
    $backend = $this->container->get('cache.backend.database')->get('pgsql_aborted');
    $outer = $this->connection->startTransaction();
    $backend->set('rolled_back', 'first');
    try {
      $this->connection->query('SELECT * FROM {pgsql_missing_table}');
      $this->fail('Querying a missing table must fail.');
    }
    catch (DatabaseException) {
    }
    // PostgreSQL treats COMMIT on an aborted transaction as ROLLBACK.
    $outer->commitOrRelease();
    $this->assertFalse($this->connection->schema()->tableExists('cache_pgsql_aborted'));
    $outer = $this->connection->startTransaction();
    $backend->set('retained', 'second');
    $this->assertSame('second', $backend->get('retained')->data);
    $outer->commitOrRelease();
  }

  /**
   * Tests invalidation when another service drops or renames a verified table.
   */
  #[DataProvider('schemaChangeProvider')]
  public function testSchemaChange(bool $rename): void {
    $backend = $this->container->get('cache.backend.database')->get('pgsql_schema');
    $other_backend = clone $backend;
    $backend->set('item', 'old');
    $outer = $this->connection->startTransaction();
    $this->assertSame('old', $backend->get('item')->data);
    if ($rename) {
      $this->connection->schema()->renameTable('cache_pgsql_schema', 'cache_pgsql_renamed');
    }
    else {
      $other_backend->removeBin();
    }
    $this->assertFalse($backend->get('item'));
    $this->assertEquals(1, $this->connection->query('SELECT 1')->fetchField());
    $backend->set('item', 'new');
    $this->assertSame('new', $backend->get('item')->data);
    $outer->commitOrRelease();
  }

  /**
   * Provides schema changes that remove a table from its original name.
   */
  public static function schemaChangeProvider(): array {
    return [
      'drop' => [FALSE],
      'rename' => [TRUE],
    ];
  }

  /**
   * Provides root transaction and nested savepoint rollbacks.
   */
  public static function rollbackProvider(): array {
    return [
      'root transaction' => [FALSE, FALSE],
      'nested savepoint' => [TRUE, FALSE],
      'root transaction, another backend creates the table' => [FALSE, TRUE],
      'nested savepoint, another backend creates the table' => [TRUE, TRUE],
    ];
  }

}
