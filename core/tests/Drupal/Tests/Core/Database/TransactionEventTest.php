<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Event\TransactionCommitEvent;
use Drupal\Core\Database\Event\TransactionCommitFailureEvent;
use Drupal\Core\Database\Event\TransactionEvent;
use Drupal\Core\Database\Event\TransactionReleaseSavepointEvent;
use Drupal\Core\Database\Event\TransactionReleaseSavepointFailureEvent;
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
  }

  /**
   * Tests releasing a savepoint.
   */
  public function testTransactionReleaseSavepoint(): void {
    $tx = $this->connection->startTransaction();
    $savepoint = $this->connection->startTransaction('foo');
    $savepoint->commitOrRelease();
    $this->assertSame(TransactionReleaseSavepointEvent::class, get_class($this->subscriber->lastEvent));
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
    unset($tx);
  }

}
