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
    $this->assertMatchesRegularExpression("/^default default .*\\\\drupal_transaction$/", \Drupal::keyValue('TransactionEventTest')->get('event_data'));
    $tx->commitOrRelease();
  }

  /**
   * Tests adding a savepoint.
   */
  public function testTransactionSavepoint(): void {
    $tx = $this->connection->startTransaction();
    $savepoint = $this->connection->startTransaction();
    $this->assertMatchesRegularExpression("/^default default .*\\\\savepoint_1 stack: .*\\\\drupal_transaction$/", \Drupal::keyValue('TransactionEventTest')->get('event_data'));
    $savepoint->commitOrRelease();
    $tx->commitOrRelease();
  }

  /**
   * Tests committing a transaction.
   */
  public function testTransactionCommit(): void {
    $tx = $this->connection->startTransaction();
    $tx->commitOrRelease();
    $this->assertMatchesRegularExpression("/^default default .*\\\\drupal_transaction$/", \Drupal::keyValue('TransactionEventTest')->get('event_data'));
  }

  /**
   * Tests committing a transaction after a savepoint was opened.
   */
  public function testTransactionCommitNested(): void {
    $tx = $this->connection->startTransaction();
    $savepoint = $this->connection->startTransaction('foo');
    $tx->commitOrRelease();
    $this->assertMatchesRegularExpression("/^default default .*\\\\drupal_transaction$/", \Drupal::keyValue('TransactionEventTest')->get('event_data'));
    unset($savepoint);
  }

  /**
   * Tests releasing a savepoint.
   */
  public function testTransactionReleaseSavepoint(): void {
    $tx = $this->connection->startTransaction();
    $savepoint = $this->connection->startTransaction('foo');
    $savepoint->commitOrRelease();
    $this->assertMatchesRegularExpression("/^default default .*\\\\foo stack: .*\\\\drupal_transaction$/", \Drupal::keyValue('TransactionEventTest')->get('event_data'));
    unset($tx);
  }

}
