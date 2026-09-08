<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Kernel\pgsql;

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
  public function testTableCreationRollback(bool $nested): void {
    $backend = $this->container->get('cache.backend.database')->get('pgsql_rollback');
    $table = 'cache_pgsql_rollback';
    $this->assertFalse($this->connection->schema()->tableExists($table));

    $outer = $this->connection->startTransaction();
    $transaction = $nested ? $this->connection->startTransaction() : $outer;
    $backend->set('rolled_back', 'first');
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
   * Provides root transaction and nested savepoint rollbacks.
   */
  public static function rollbackProvider(): array {
    return [
      'root transaction' => [FALSE],
      'nested savepoint' => [TRUE],
    ];
  }

}
