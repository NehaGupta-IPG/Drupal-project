<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Kernel\pgsql;

use Drupal\Core\Database\Database;
use Drupal\KernelTests\Core\Database\DriverSpecificKernelTestBase;
use Drupal\pgsql\Queue\DatabaseQueue;
use Drupal\pgsql\Queue\QueueDatabaseFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the PostgreSQL specific queue implementation.
 */
#[Group('Database')]
#[Group('Queue')]
#[RunTestsInSeparateProcesses]
class DatabaseQueueTest extends DriverSpecificKernelTestBase {

  /**
   * Tests that the queue factory returns the PostgreSQL specific queue.
   */
  public function testQueueFactoryOverride(): void {
    $factory = $this->container->get('queue.database');
    $this->assertInstanceOf(QueueDatabaseFactory::class, $factory);
    $this->assertInstanceOf(DatabaseQueue::class, $factory->get($this->randomMachineName()));
  }

  /**
   * Tests the queue contract of the atomic claimItem() implementation.
   */
  public function testClaimItem(): void {
    $queue = new DatabaseQueue($this->randomMachineName(), $this->connection);

    // Claiming from a non-existing queue table returns FALSE.
    $this->assertFalse($queue->claimItem());

    $queue->createItem(['id' => 1]);
    $queue->createItem(['id' => 2]);
    $this->assertSame(2, $queue->numberOfItems());

    // Items are claimed in FIFO order and a claimed item cannot be claimed
    // again while its lease is active.
    $item1 = $queue->claimItem();
    $this->assertEquals(['id' => 1], $item1->data);
    $item2 = $queue->claimItem();
    $this->assertEquals(['id' => 2], $item2->data);
    $this->assertFalse($queue->claimItem());

    // A released item can be claimed again.
    $this->assertTrue($queue->releaseItem($item1));
    $reclaimed = $queue->claimItem();
    $this->assertEquals($item1->item_id, $reclaimed->item_id);

    $queue->deleteItem($reclaimed);
    $queue->deleteItem($item2);
    $this->assertSame(0, $queue->numberOfItems());
  }

  /**
   * Tests that concurrent consumers skip locked items instead of racing.
   */
  public function testConcurrentClaimSkipsLockedItems(): void {
    $queue_name = $this->randomMachineName();
    $queue = new DatabaseQueue($queue_name, $this->connection);
    $queue->createItem(['id' => 1]);
    $queue->createItem(['id' => 2]);

    // Open a second, genuinely separate database session on the same target.
    $connection_info = Database::getConnectionInfo('default');
    Database::addConnectionInfo('default2', 'default', $connection_info['default']);
    $connection2 = Database::getConnection('default', 'default2');
    // If claimItem() were to wait on locked rows rather than skip them, the
    // claim below would block until the first transaction commits. Turn that
    // hang into a query failure.
    $connection2->query("SET lock_timeout = '5s'");
    $queue2 = new DatabaseQueue($queue_name, $connection2);

    // Claim the item at the head of the queue inside a transaction, keeping
    // its row exclusively locked and the claim uncommitted.
    $transaction = $this->connection->startTransaction();
    $first = $queue->claimItem();
    $this->assertEquals(['id' => 1], $first->data);

    // A concurrent consumer must skip the locked head-of-queue item and
    // claim the next one without blocking.
    $second = $queue2->claimItem();
    $this->assertNotFalse($second);
    $this->assertEquals(['id' => 2], $second->data);

    // Commit the first claim. Both items are now leased, so nothing is left
    // to claim on either session.
    $transaction->commitOrRelease();
    $this->assertFalse($queue->claimItem());
    $this->assertFalse($queue2->claimItem());

    Database::removeConnection('default2');
  }

}
