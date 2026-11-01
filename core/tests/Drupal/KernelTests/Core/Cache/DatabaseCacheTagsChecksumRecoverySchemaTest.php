<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Cache;

use Drupal\Core\Cache\DatabaseCacheTagsChecksum;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\KernelTests\Core\Cache\Fixtures\RecoveryDriver\Connection as FaultConnection;
use Drupal\KernelTests\Core\Database\DatabaseTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the narrowly scoped SQL missing-table fallback.
 */
#[Group('Cache')]
#[RunTestsInSeparateProcesses]
class DatabaseCacheTagsChecksumRecoverySchemaTest extends DatabaseTestBase {

  /**
   * Tests creation of a missing table and retry of the first tag.
   */
  public function testMissingTableDoesNotLoseFirstInvalidation(): void {
    // Only the isolated Kernel database is affected.
    if ($this->connection->schema()->tableExists('cachetags')) {
      $this->connection->schema()->dropTable('cachetags');
    }
    $checksum = new DatabaseCacheTagsChecksum($this->connection);
    $checksum->invalidateTags(['cold:a', 'cold:b']);
    foreach (['cold:a', 'cold:b'] as $tag) {
      $count = $this->connection->select('cachetags', 'c')->fields('c', ['invalidations'])->condition('tag', $tag)->execute()->fetchField();
      $this->assertSame(1, (int) $count);
    }
  }

  /**
   * Tests that an existing table cannot suppress an unrelated SQL error.
   */
  public function testSqlFailureWithExistingTablePropagates(): void {
    if ($this->connection->getConnectionOptions()['driver'] !== 'sqlite') {
      $this->markTestSkipped('This test uses the SQLite fault-injection driver.');
    }
    $key = 'cache_sql_error_probe';
    $options = $this->connection->getConnectionOptions();
    $options['namespace'] = 'Drupal\\KernelTests\\Core\\Cache\\Fixtures\\RecoveryDriver';
    $options['autoload'] = 'core/tests/Drupal/KernelTests/Core/Cache/Fixtures/RecoveryDriver/';
    Database::addConnectionInfo($key, 'default', $options);
    try {
      $probe = Database::getConnection('default', $key);
      $this->assertInstanceOf(FaultConnection::class, $probe);
      $checksum = new DatabaseCacheTagsChecksum($probe);
      $checksum->getCurrentChecksum(['sql:error']);
      $error = new DatabaseExceptionWrapper('Unrelated SQL failure');
      $probe->failure = $error;
      $caught = NULL;
      try {
        $checksum->invalidateTags(['sql:error']);
      }
      catch (\Throwable $actual) {
        $caught = $actual;
      }
      $this->assertSame($error, $caught);
    }
    finally {
      Database::removeConnection($key);
    }
  }

}
