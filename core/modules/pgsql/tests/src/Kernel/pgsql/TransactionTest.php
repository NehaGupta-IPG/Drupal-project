<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Kernel\pgsql;

use Drupal\Core\Database\Transaction\TransactionManagerBase;
use Drupal\KernelTests\Core\Database\DriverSpecificTransactionTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests transaction for the PostgreSQL driver.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class TransactionTest extends DriverSpecificTransactionTestBase {

  /**
   * Tests that a root commit failing on the client changes the version.
   */
  public function testFailedCommitChangesTransactionVersion(): void {
    // A deferred constraint is only checked on commit, so the client commit
    // throws an exception.
    $this->connection->query('CREATE TABLE {test_deferred} (id integer UNIQUE DEFERRABLE INITIALLY DEFERRED)');

    $manager = $this->connection->transactionManager();
    $this->assertInstanceOf(TransactionManagerBase::class, $manager);
    $version = $manager->getTransactionVersion();

    $transaction = $this->connection->startTransaction();
    $this->connection->query('INSERT INTO {test_deferred} (id) VALUES (1), (1)');
    try {
      $transaction->commitOrRelease();
      $this->fail('Expected exception was not thrown.');
    }
    catch (\PDOException $e) {
      $this->assertSame('23505', $e->getCode());
    }
    $this->assertSame($version + 1, $manager->getTransactionVersion());
    $this->assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM {test_deferred}')->fetchField());

    // Clean up the stack so the Transaction object can be destructed.
    $manager->voidClientTransaction();
    unset($transaction);
  }

}
