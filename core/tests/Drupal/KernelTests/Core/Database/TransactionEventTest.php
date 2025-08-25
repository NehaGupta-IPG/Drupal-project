<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\Event\TransactionEvent;
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
    $this->assertMatchesRegularExpression("/^.*TransactionBeginEvent default default .*\\\\drupal_transaction$/", \Drupal::keyValue('TransactionEventTest')->get('event_data'));
    $tx->commitOrRelease();
  }

  /**
   * Tests adding a savepoint.
   */
  public function testTransactionSavepoint(): void {
    $tx = $this->connection->startTransaction();
    $savepoint = $this->connection->startTransaction();
    $this->assertMatchesRegularExpression("/^.*TransactionSavepointEvent default default .*\\\\savepoint_1 stack: .*\\\\drupal_transaction > .*\\\\savepoint_1$/", \Drupal::keyValue('TransactionEventTest')->get('event_data'));
    $savepoint->commitOrRelease();
    $tx->commitOrRelease();
  }

  /**
   * Tests committing a transaction.
   */
  public function testTransactionCommit(): void {
    $tx = $this->connection->startTransaction();
    $tx->commitOrRelease();
    $this->assertMatchesRegularExpression("/^.*TransactionCommitEvent default default .*\\\\drupal_transaction commit-type: Explicit$/", \Drupal::keyValue('TransactionEventTest')->get('event_data'));
  }

  /**
   * Tests implicitly committing a transaction via object destruction.
   */
  public function testTransactionImplicitCommit(): void {
    $tx = $this->connection->startTransaction();
    unset($tx);
    $this->assertMatchesRegularExpression("/^.*TransactionCommitEvent default default .*\\\\drupal_transaction commit-type: ImplicitOnDestruct$/", \Drupal::keyValue('TransactionEventTest')->get('event_data'));
  }

  /**
   * Tests committing a transaction after a savepoint was opened.
   */
  public function testTransactionCommitNested(): void {
    $tx = $this->connection->startTransaction();
    $savepoint = $this->connection->startTransaction('foo');
    $tx->commitOrRelease();
    $this->assertMatchesRegularExpression("/^.*TransactionCommitEvent default default .*\\\\drupal_transaction commit-type: Explicit$/", \Drupal::keyValue('TransactionEventTest')->get('event_data'));
    unset($savepoint);
  }

  /**
   * Tests DDL auto-commit.
   */
  public function testAutoCommitOnDataDefinitionLanguageStatement(): void {
    if ($this->connection->supportsTransactionalDDL()) {
      $this->markTestSkipped('This test is only relevant for databases that do not support transactional DDL.');
    }

    $table = [
      'fields' => [
        'id' => [
          'type' => 'serial',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
      ],
      'primary key' => ['id'],
    ];

    $tx = $this->connection->startTransaction();
    $this->connection->schema()->createTable('database_test_00', $table);
    $this->assertMatchesRegularExpression("/^.*TransactionAutoCommitEvent default default .*\\\\drupal_transaction$/", \Drupal::keyValue('TransactionEventTest')->get('event_data'));
    unset($tx);
    $this->assertMatchesRegularExpression("/^.*TransactionCommitEvent default default .*\\\\drupal_transaction commit-type: AutomaticUponDDL$/", \Drupal::keyValue('TransactionEventTest')->get('event_data'));
  }

  /**
   * Tests releasing a savepoint.
   */
  public function testTransactionReleaseSavepoint(): void {
    $tx = $this->connection->startTransaction();
    $savepoint = $this->connection->startTransaction('foo');
    $savepoint->commitOrRelease();
    $this->assertMatchesRegularExpression("/^.*TransactionReleaseSavepointEvent default default .*\\\\foo stack: .*\\\\drupal_transaction$/", \Drupal::keyValue('TransactionEventTest')->get('event_data'));
    unset($tx);
  }

  /**
   * Tests rolling back a transaction.
   */
  public function testTransactionRollback(): void {
    $tx = $this->connection->startTransaction();
    $tx->rollback();
    $this->assertMatchesRegularExpression("/^.*TransactionRollbackEvent default default .*\\\\drupal_transaction$/", \Drupal::keyValue('TransactionEventTest')->get('event_data'));
  }

  /**
   * Tests rolling back a transaction to a savepoint.
   */
  public function testTransactionRollbackToSavepoint(): void {
    $tx = $this->connection->startTransaction();
    $savepoint = $this->connection->startTransaction('foo');
    $savepoint->rollback();
    $this->assertMatchesRegularExpression("/^.*TransactionRollbackToSavepointEvent default default .*\\\\foo stack: .*\\\\drupal_transaction$/", \Drupal::keyValue('TransactionEventTest')->get('event_data'));
    unset($tx);
  }

}
