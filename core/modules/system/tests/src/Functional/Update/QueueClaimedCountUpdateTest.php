<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

use Drupal\Core\Database\Database;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade path for the queue table update.
 *
 * @group Update
 */
class QueueClaimedCountUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests the upgrade path for adding claimed_count to the queue table.
   */
  public function testRunUpdates(): void {
    $connection = Database::getConnection();
    $this->assertFalse($connection->schema()->fieldExists('queue', 'claimed_count'));
    $queue = \Drupal::queue('test');
    $queue->createQueue();
    $queue->createItem('test');
    $this->runUpdates();
    $this->assertTrue($connection->schema()->fieldExists('queue', 'claimed_count'));
    $query = $connection->select('queue', 'q');
    $query->addField('q', 'claimed_count');
    $this->assertEquals(0, $query->execute()->fetchField());
    // Make sure the queue table is created with new column if missing.
    $connection->schema()->dropTable('queue');
    $this->assertFalse($connection->schema()->tableExists('queue'));
    $queue->createQueue();
    $queue->createItem('test2');
    $this->assertEquals(1, $queue->numberOfItems());
    $this->assertTrue($connection->schema()->fieldExists('queue', 'claimed_count'));
  }

}
