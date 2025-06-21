<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\Event\TransactionBeginEvent;
use Drupal\Core\Database\Event\TransactionEvent;
use Drupal\Core\Database\Event\TransactionSavepointEvent;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Transaction events.
 */
#[Group('Database')]
class TransactionEventTest extends DatabaseTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->connection->enableEvents(TransactionEvent::all());
  }

  /**
   * Tests transaction beginning.
   */
  public function testTransactionBegin(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches("/^default default .*\\\\drupal_transaction/");
    // phpcs:ignore DrupalPractice.CodeAnalysis.VariableAnalysis.UnusedVariable
    $tx = $this->connection->startTransaction();
  }

  /**
   * Tests adding a savepoint.
   */
  public function testTransactionSavepoint(): void {
    $this->connection->disableEvents([
      TransactionBeginEvent::class,
    ]);
    // phpcs:ignore DrupalPractice.CodeAnalysis.VariableAnalysis.UnusedVariable
    $tx = $this->connection->startTransaction();
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches("/^default default .*\\\\savepoint_1 stack: .*\\\\drupal_transaction/");
    // phpcs:ignore DrupalPractice.CodeAnalysis.VariableAnalysis.UnusedVariable
    $savepoint = $this->connection->startTransaction();
  }

  /**
   * Tests committing a transaction after a savepoint was opened.
   */
  public function testTransactionCommit(): void {
    $this->connection->disableEvents([
      TransactionBeginEvent::class,
      TransactionSavepointEvent::class,
    ]);
    $tx = $this->connection->startTransaction();
    // phpcs:ignore DrupalPractice.CodeAnalysis.VariableAnalysis.UnusedVariable
    $savepoint = $this->connection->startTransaction('foo');
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches("/^default default .*\\\\drupal_transaction stack: .*\\\\drupal_transaction > .*\\\\foo/");
    $tx->commitOrRelease();
  }

}
