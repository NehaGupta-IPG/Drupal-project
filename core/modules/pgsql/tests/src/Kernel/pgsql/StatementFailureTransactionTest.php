<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Kernel\pgsql;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\KernelTests\Core\Database\DriverSpecificDatabaseTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests transaction behavior on statement failure on PostgreSQL.
 *
 * PostgreSQL aborts the whole transaction when a statement fails. Callers
 * that catch a statement failure and continue an enclosing transaction must
 * protect the statement with a savepoint, created with
 * Connection::startTransaction().
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class StatementFailureTransactionTest extends DriverSpecificDatabaseTestBase {

  /**
   * Runs a query on a missing table, catching the resulting exception.
   */
  protected function queryMissingTable(): void {
    try {
      $this->connection->query('SELECT 1 FROM {pgsql_statement_failure_missing}');
      $this->fail('Querying a missing table should throw.');
    }
    catch (DatabaseExceptionWrapper) {
      // Expected: the table does not exist.
    }
  }

  /**
   * Tests that a failed statement aborts the transaction.
   */
  public function testAbortedTransaction(): void {
    $transaction = $this->connection->startTransaction();
    $this->queryMissingTable();
    try {
      $this->connection->query('SELECT 1');
      $this->fail('The transaction should be aborted.');
    }
    catch (DatabaseExceptionWrapper) {
      // Expected: current transaction is aborted (SQLSTATE 25P02).
    }
    $transaction->rollBack();

    // A new transaction is usable again.
    $transaction = $this->connection->startTransaction();
    $this->insertRow('Buddy');
    $transaction->commitOrRelease();
    $this->assertRowPresent('Buddy');
  }

  /**
   * Tests that an explicit savepoint recovers an aborted transaction.
   */
  public function testExplicitSavepointRecovery(): void {
    $transaction = $this->connection->startTransaction();
    $this->insertRow('Frank');
    $savepoint = $this->connection->startTransaction();
    $this->queryMissingTable();
    $savepoint->rollBack();

    // The rollback to the explicit savepoint recovered the transaction.
    $this->insertRow('Kim');
    $transaction->commitOrRelease();
    $this->assertRowPresent('Frank');
    $this->assertRowPresent('Kim');
  }

  /**
   * Tests that committing an aborted transaction rolls it back.
   */
  public function testCommitOfAbortedTransaction(): void {
    $transaction = $this->connection->startTransaction();
    $this->insertRow('Grace');
    $this->queryMissingTable();

    // Committing an aborted transaction does not throw, but the work is
    // rolled back by the database.
    $transaction->commitOrRelease();
    $this->assertRowAbsent('Grace');
  }

  /**
   * Inserts a row in the {test} table.
   */
  protected function insertRow(string $name): void {
    $this->connection->insert('test')->fields(['name' => $name])->execute();
  }

  /**
   * Asserts a row is present in the {test} table.
   */
  protected function assertRowPresent(string $name): void {
    $present = (bool) $this->connection->select('test')
      ->condition('name', $name)
      ->countQuery()->execute()->fetchField();
    $this->assertTrue($present, "Row '$name' is present.");
  }

  /**
   * Asserts a row is absent from the {test} table.
   */
  protected function assertRowAbsent(string $name): void {
    $present = (bool) $this->connection->select('test')
      ->condition('name', $name)
      ->countQuery()->execute()->fetchField();
    $this->assertFalse($present, "Row '$name' is absent.");
  }

}
