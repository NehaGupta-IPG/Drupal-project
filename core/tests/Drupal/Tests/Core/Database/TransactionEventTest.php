<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Event\TransactionCommitEvent;
use Drupal\Core\Database\Event\TransactionCommitFailureEvent;
use Drupal\Core\Database\Event\TransactionEvent;
use Drupal\Core\Database\Event\TransactionReleaseSavepointEvent;
use Drupal\Core\Database\Event\TransactionReleaseSavepointFailureEvent;
use Drupal\Core\Database\Event\TransactionRollbackEvent;
use Drupal\Core\Database\Event\TransactionRollbackFailureEvent;
use Drupal\Core\Database\Event\TransactionRollbackToSavepointEvent;
use Drupal\Core\Database\Event\TransactionRollbackToSavepointFailureEvent;
use Drupal\Core\Database\Transaction\ClientConnectionTransactionState;
use Drupal\Core\Database\Transaction\CommitType;
use Drupal\Core\Database\TransactionCommitFailedException;
use Drupal\Core\DependencyInjection\Container;
use Drupal\Tests\Core\Database\Stub\EventSubscriber\StubTransactionEventSubscriber;
use Drupal\Tests\Core\Database\Stub\StubConnection;
use Drupal\Tests\Core\Database\Stub\StubPDO;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests transaction events.
 */
#[Group('Database')]
class TransactionEventTest extends UnitTestCase {

  /**
   * A database connection.
   */
  protected Connection $connection;

  /**
   * A subscriber for transaction events.
   */
  protected StubTransactionEventSubscriber $subscriber;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->connection = new StubConnection($this->createMock(StubPDO::class), []);
    $this->connection->enableEvents(TransactionEvent::all());

    $container = new Container();
    $dispatcher = new EventDispatcher();
    $this->subscriber = new StubTransactionEventSubscriber();
    $dispatcher->addSubscriber($this->subscriber);
    $container->set('event_dispatcher', $dispatcher);
    \Drupal::setContainer($container);
  }

  /**
   * Tests committing a transaction.
   */
  public function testTransactionCommit(): void {
    $tx = $this->connection->startTransaction();
    $tx->commitOrRelease();
    $this->assertSame(TransactionCommitEvent::class, get_class($this->subscriber->lastEvent));
    $this->assertSame('drupal_transaction', $this->subscriber->lastEvent->name);
    $this->assertSame(ClientConnectionTransactionState::Committed, $this->subscriber->lastEvent->transactionState);
    $this->assertSame(CommitType::Explicit, $this->subscriber->lastEvent->type);
    $this->assertCount(0, $this->subscriber->lastEvent->stackItems);
  }

  /**
   * Tests committing a transaction via object destruction.
   */
  public function testTransactionCommitOnDestruct(): void {
    $tx = $this->connection->startTransaction();
    unset($tx);
    $this->assertSame(TransactionCommitEvent::class, get_class($this->subscriber->lastEvent));
    $this->assertSame('drupal_transaction', $this->subscriber->lastEvent->name);
    $this->assertSame(ClientConnectionTransactionState::Committed, $this->subscriber->lastEvent->transactionState);
    $this->assertSame(CommitType::ImplicitOnDestruct, $this->subscriber->lastEvent->type);
    $this->assertCount(0, $this->subscriber->lastEvent->stackItems);
  }

  /**
   * Tests a transaction commit failure.
   */
  public function testTransactionCommitFailure(): void {
    $tx = $this->connection->startTransaction();
    $this->connection->transactionManager()->success = FALSE;
    try {
      $tx->commitOrRelease();
    }
    catch (TransactionCommitFailedException) {
      // Expected, do nothing.
    }
    $this->assertSame(TransactionCommitFailureEvent::class, get_class($this->subscriber->lastEvent));
    $this->assertSame('drupal_transaction', $this->subscriber->lastEvent->name);
    $this->assertSame(ClientConnectionTransactionState::CommitFailed, $this->subscriber->lastEvent->transactionState);
    $this->assertSame(CommitType::Explicit, $this->subscriber->lastEvent->type);
    $this->assertCount(0, $this->subscriber->lastEvent->stackItems);
  }

  /**
   * Tests releasing a savepoint.
   */
  public function testTransactionReleaseSavepoint(): void {
    $tx = $this->connection->startTransaction();
    $savepoint = $this->connection->startTransaction('foo');
    $savepoint->commitOrRelease();
    $this->assertSame(TransactionReleaseSavepointEvent::class, get_class($this->subscriber->lastEvent));
    $this->assertSame('foo', $this->subscriber->lastEvent->name);
    $this->assertSame(ClientConnectionTransactionState::Active, $this->subscriber->lastEvent->transactionState);
    $this->assertSame(CommitType::Explicit, $this->subscriber->lastEvent->type);
    $this->assertCount(1, $this->subscriber->lastEvent->stackItems);
    unset($tx);
  }

  /**
   * Tests a transaction release savepoint failure.
   */
  public function testTransactionReleaseSavepointFailure(): void {
    $tx = $this->connection->startTransaction();
    $savepoint = $this->connection->startTransaction('foo');
    $this->connection->transactionManager()->success = FALSE;
    $savepoint->commitOrRelease();
    $this->assertSame(TransactionReleaseSavepointFailureEvent::class, get_class($this->subscriber->lastEvent));
    $this->assertSame('foo', $this->subscriber->lastEvent->name);
    $this->assertSame(ClientConnectionTransactionState::CommitFailed, $this->subscriber->lastEvent->transactionState);
    $this->assertSame(CommitType::Explicit, $this->subscriber->lastEvent->type);
    $this->assertCount(0, $this->subscriber->lastEvent->stackItems);
    unset($tx);
  }

  /**
   * Tests rolling back a transaction.
   */
  public function testTransactionRollback(): void {
    $tx = $this->connection->startTransaction();
    $tx->rollback();
    $this->assertSame(TransactionRollbackEvent::class, get_class($this->subscriber->lastEvent));
    $this->assertSame('drupal_transaction', $this->subscriber->lastEvent->name);
    $this->assertSame(ClientConnectionTransactionState::RolledBack, $this->subscriber->lastEvent->transactionState);
    $this->assertCount(0, $this->subscriber->lastEvent->stackItems);
  }

  /**
   * Tests a transaction rollback failure.
   */
  public function testTransactionRollbackFailure(): void {
    $tx = $this->connection->startTransaction();
    $this->connection->transactionManager()->success = FALSE;
    $tx->rollback();
    $this->assertSame(TransactionRollbackFailureEvent::class, get_class($this->subscriber->lastEvent));
    $this->assertSame('drupal_transaction', $this->subscriber->lastEvent->name);
    $this->assertSame(ClientConnectionTransactionState::RollbackFailed, $this->subscriber->lastEvent->transactionState);
    $this->assertCount(0, $this->subscriber->lastEvent->stackItems);
  }

  /**
   * Tests rolling back a transaction to a savepoint.
   */
  public function testTransactionRollbackToSavepoint(): void {
    $tx = $this->connection->startTransaction();
    $savepoint = $this->connection->startTransaction('foo');
    $savepoint->rollback();
    $this->assertSame(TransactionRollbackToSavepointEvent::class, get_class($this->subscriber->lastEvent));
    $this->assertSame('foo', $this->subscriber->lastEvent->name);
    $this->assertSame(ClientConnectionTransactionState::Active, $this->subscriber->lastEvent->transactionState);
    $this->assertCount(1, $this->subscriber->lastEvent->stackItems);
  }

  /**
   * Tests failure when rolling back a transaction to a savepoint.
   */
  public function testTransactionRollbackToSavepointFailure(): void {
    $tx = $this->connection->startTransaction();
    $savepoint = $this->connection->startTransaction('foo');
    $this->connection->transactionManager()->success = FALSE;
    $savepoint->rollback();
    $this->assertSame(TransactionRollbackToSavepointFailureEvent::class, get_class($this->subscriber->lastEvent));
    $this->assertSame('foo', $this->subscriber->lastEvent->name);
    $this->assertSame(ClientConnectionTransactionState::RollbackFailed, $this->subscriber->lastEvent->transactionState);
    $this->assertCount(0, $this->subscriber->lastEvent->stackItems);
  }

}
