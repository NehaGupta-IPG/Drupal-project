<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\Event\TransactionBeginEvent;
use Drupal\Core\Database\Event\TransactionCommitEvent;
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
    $tx = $this->connection->startTransaction();
    $this->assertMatchesRegularExpression("/^default default .*\\\\drupal_transaction/", \Drupal::keyValue('TransactionEventTest')->get('event_data'));
    $tx->commitOrRelease();
  }

  /**
   * Tests adding a savepoint.
   */
  public function testTransactionSavepoint(): void {
    $this->connection->disableEvents([
      TransactionBeginEvent::class,
      TransactionCommitEvent::class,
    ]);
    $tx = $this->connection->startTransaction();
    $savepoint = $this->connection->startTransaction();
    $this->assertMatchesRegularExpression("/^default default .*\\\\savepoint_1 stack: .*\\\\drupal_transaction/", \Drupal::keyValue('TransactionEventTest')->get('event_data'));
    $savepoint->commitOrRelease();
    $tx->commitOrRelease();
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
    $savepoint = $this->connection->startTransaction('foo');
    $tx->commitOrRelease();
    $this->assertMatchesRegularExpression("/^default default .*\\\\drupal_transaction stack: .*\\\\drupal_transaction/", \Drupal::keyValue('TransactionEventTest')->get('event_data'));
    unset($savepoint);
  }

}
