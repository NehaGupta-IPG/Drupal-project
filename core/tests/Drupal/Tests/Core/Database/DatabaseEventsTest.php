<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Event\DatabaseEvent;
use Drupal\Core\Database\Event\StatementEvent;
use Drupal\Core\Database\Event\StatementExecutionEndEvent;
use Drupal\Core\Database\Event\StatementExecutionFailureEvent;
use Drupal\Core\Database\Event\StatementExecutionStartEvent;
use Drupal\Core\EventDispatcher\EventDispatcherFactory;
use Drupal\Core\EventDispatcher\EventDispatcherFactoryInterface;
use Drupal\Core\EventDispatcher\EventDispatcherFactoryStage;
use Drupal\Tests\Core\Database\Stub\StubConnection;
use Drupal\Tests\Core\Database\Stub\StubPDO;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the database events.
 *
 * We need to run these tests in isolation since they instantiate the event
 * dispatcher via the factory that uses a static to hold it.
 */
#[CoversClass(Connection::class)]
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class DatabaseEventsTest extends UnitTestCase {

  /**
   * A database connection.
   */
  protected Connection $connection;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->connection = new StubConnection($this->createStub(StubPDO::class), [], ['', ''], new EventDispatcherFactory());
  }

  /**
   * Tests event enabling and disabling.
   *
   * @legacy-covers ::isEventEnabled
   * @legacy-covers ::enableEvents
   * @legacy-covers ::disableEvents
   */
  public function testEventEnablingAndDisabling(): void {
    $this->connection->enableEvents(StatementEvent::all());
    $this->assertTrue($this->connection->isEventEnabled(StatementExecutionStartEvent::class));
    $this->assertTrue($this->connection->isEventEnabled(StatementExecutionEndEvent::class));
    $this->assertTrue($this->connection->isEventEnabled(StatementExecutionFailureEvent::class));
    $this->connection->disableEvents([
      StatementExecutionEndEvent::class,
    ]);
    $this->assertTrue($this->connection->isEventEnabled(StatementExecutionStartEvent::class));
    $this->assertFalse($this->connection->isEventEnabled(StatementExecutionEndEvent::class));
    $this->assertTrue($this->connection->isEventEnabled(StatementExecutionFailureEvent::class));
    $this->connection->disableEvents(StatementEvent::all());
    $this->assertFalse($this->connection->isEventEnabled(StatementExecutionStartEvent::class));
    $this->assertFalse($this->connection->isEventEnabled(StatementExecutionEndEvent::class));
    $this->assertFalse($this->connection->isEventEnabled(StatementExecutionFailureEvent::class));
  }

  /**
   * Tests enable invalid event.
   *
   * @legacy-covers ::enableEvents
   */
  public function testEnableInvalidEvent(): void {
    $this->expectException(\AssertionError::class);
    $this->expectExceptionMessage('Event class foo does not exist');
    $this->connection->enableEvents(['foo']);
  }

  /**
   * Tests disable invalid event.
   *
   * @legacy-covers ::disableEvents
   */
  public function testDisableInvalidEvent(): void {
    $this->expectException(\AssertionError::class);
    $this->expectExceptionMessage('Event class bar does not exist');
    $this->connection->disableEvents(['bar']);
  }

  /**
   * Tests event dispatching when no container available.
   *
   * @legacy-covers ::dispatchEvent
   */
  public function testEventDispatchingWhenNoContainerAvailable(): void {
    $this->connection->dispatchEvent($this->createMock(DatabaseEvent::class));
    $reflectedProperty = new \ReflectionProperty($this->connection, 'eventDispatcher');
    $eventDispatcher = $reflectedProperty->getValue($this->connection);
    $this->assertInstanceOf(EventDispatcherFactoryInterface::class, $eventDispatcher);
    $this->assertSame(EventDispatcherFactoryStage::PreBootstrap, $eventDispatcher->getInstanceStage());
  }

}
