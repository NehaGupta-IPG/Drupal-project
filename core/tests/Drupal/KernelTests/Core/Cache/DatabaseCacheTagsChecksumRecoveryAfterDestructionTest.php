<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Cache;

use Drupal\Core\Cache\DatabaseCacheTagsChecksum;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\Transaction;
use Drupal\KernelTests\Core\Cache\Fixtures\RecoveryDriver\Connection as FaultConnection;
use Drupal\KernelTests\Core\Database\DatabaseTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Checks errors and subsequent reads after actual client destruction.
 */
#[Group('Cache')]
#[RunTestsInSeparateProcesses]
class DatabaseCacheTagsChecksumRecoveryAfterDestructionTest extends DatabaseTestBase {

  /**
   * The fault-injecting SQLite connection.
   */
  private FaultConnection $probe;
  /**
   * The checksum service under test.
   */
  private DatabaseCacheTagsChecksum $checksum;
  private const KEY = 'cache_destroyed_probe';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    FaultConnection::$openFailure = FaultConnection::$retryFailure = NULL;
    $options = $this->connection->getConnectionOptions();
    if ($options['driver'] !== 'sqlite') {
      $this->markTestSkipped('This test uses the SQLite fault-injection driver.');
    }
    $options['namespace'] = 'Drupal\\KernelTests\\Core\\Cache\\Fixtures\\RecoveryDriver';
    $options['autoload'] = 'core/tests/Drupal/KernelTests/Core/Cache/Fixtures/RecoveryDriver/';
    Database::addConnectionInfo(self::KEY, 'default', $options);
    $probe = Database::getConnection('default', self::KEY);
    $this->assertInstanceOf(FaultConnection::class, $probe);
    $this->probe = $probe;
    $this->checksum = new DatabaseCacheTagsChecksum($this->probe);
    $this->checksum->getCurrentChecksum(['probe:destroyed']);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    FaultConnection::$openFailure = FaultConnection::$retryFailure = NULL;
    Database::removeConnection(self::KEY);
    parent::tearDown();
  }

  /**
   * Completes SQL and destroys its client while retaining the transaction.
   */
  private function completeAndDestroyClient(): Transaction {
    $transaction = $this->probe->startTransaction();
    $this->probe->insert('test')->fields(['name' => 'destroyed-probe'])->execute();
    $this->checksum->invalidateTags(['probe:destroyed']);
    $transaction->commitOrRelease();
    $this->probe->__destruct();
    return $transaction;
  }

  /**
   * Reads the persistent tag invalidation count.
   */
  private function counter(): int {
    return (int) $this->connection->select('cachetags', 'c')->fields('c', ['invalidations'])->condition('tag', 'probe:destroyed')->execute()->fetchField();
  }

  /**
   * Provides reconnection and retry failures after client destruction.
   */
  public static function failures(): array {
    return ['reconnect' => [TRUE], 'retry' => [FALSE]];
  }

  /**
   * Tests that a recovery failure is not masked by the destroyed client.
   */
  #[DataProvider('failures')]
  public function testDestroyedClientPreservesRecoveryError(bool $opening): void {
    $transaction = $this->completeAndDestroyClient();
    $error = new \RuntimeException($opening ? 'Reconnection failed' : 'Retry failed');
    if ($opening) {
      FaultConnection::$openFailure = $error;
    }
    else {
      FaultConnection::$retryFailure = $error;
    }
    $caught = NULL;
    try {
      unset($transaction);
    }
    catch (\Throwable $actual) {
      $caught = $actual;
    }
    FaultConnection::$openFailure = FaultConnection::$retryFailure = NULL;
    $this->assertSame(0, $this->counter());
    $this->assertSame($error, $caught, 'Recovery must preserve its failure instead of throwing a secondary error through the destroyed connection.');
  }

  /**
   * Tests that the recovered service retains a usable connection.
   */
  public function testRecoveredChecksumRemainsReadable(): void {
    $transaction = $this->completeAndDestroyClient();
    unset($transaction);
    $this->assertSame(1, $this->counter());
    $caught = NULL;
    $value = NULL;
    try {
      $value = $this->checksum->getCurrentChecksum(['probe:destroyed']);
    }
    catch (\Throwable $actual) {
      $caught = $actual;
    }
    $this->assertNull($caught, 'The recovered service must support subsequent checksum reads.');
    $this->assertSame('1', $value);
  }

}
