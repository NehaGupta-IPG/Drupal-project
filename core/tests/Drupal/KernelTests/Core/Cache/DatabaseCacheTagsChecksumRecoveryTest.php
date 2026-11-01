<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\DatabaseBackend;
use Drupal\Core\Cache\DatabaseCacheTagsChecksum;
use Drupal\Core\Database\Database;
use Drupal\KernelTests\Core\Database\DatabaseTestBase;
use Drupal\KernelTests\Core\Cache\Fixtures\RecoveryDriver\Connection as FaultConnection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests recovery patch #3590996 against real isolated SQLite storage.
 */
#[Group('Cache')]
#[RunTestsInSeparateProcesses]
class DatabaseCacheTagsChecksumRecoveryTest extends DatabaseTestBase {

  /**
   * The fault-injecting SQLite connection.
   */
  private FaultConnection $probe;
  /**
   * The checksum service under test.
   */
  private DatabaseCacheTagsChecksum $checksum;
  private const KEY = 'cache_recovery_probe';

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
    $this->checksum->getCurrentChecksum(['probe:a', 'probe:b', 'probe:c']);
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
   * Reads the persistent tag invalidation count.
   */
  private function counter(string $tag): int {
    return (int) $this->connection->select('cachetags', 'c')->fields('c', ['invalidations'])->condition('tag', $tag)->execute()->fetchField();
  }

  /**
   * Creates a real backend with the supplied checksum service.
   */
  private function backend(DatabaseCacheTagsChecksum $checksum): DatabaseBackend {
    return new DatabaseBackend($this->connection, $checksum, 'recovery_probe', $this->container->get('serialization.phpserialize'), $this->container->get('datetime.time'));
  }

  /**
   * Tests normal persistent invalidation without reconnecting.
   */
  public function testNormalInvalidation(): void {
    $backend = $this->backend($this->checksum);
    $backend->set('listing', 'before', Cache::PERMANENT, ['probe:a']);
    $this->assertNotFalse($backend->get('listing'));
    $this->checksum->invalidateTags(['probe:a']);
    $this->assertSame(1, $this->counter('probe:a'));
    $fresh = $this->backend(new DatabaseCacheTagsChecksum($this->connection));
    $this->assertFalse($fresh->get('listing'));
    $this->assertSame($this->probe, Database::getConnection('default', self::KEY));
  }

  /**
   * Provides SQL outcomes and client-destruction conditions.
   */
  public static function completions(): array {
    return [
      'commit' => [TRUE, FALSE],
      'rollback' => [FALSE, FALSE],
      'commit_destroyed_connection' => [TRUE, TRUE],
      'rollback_destroyed_connection' => [FALSE, TRUE],
    ];
  }

  /**
   * Tests callback-driven invalidation after commit or rollback.
   */
  #[DataProvider('completions')]
  public function testDelayedInvalidation(bool $commit, bool $destroy): void {
    $backend = $this->backend($this->checksum);
    $backend->set('listing', 'before', Cache::PERMANENT, ['probe:a']);
    $transaction = $this->probe->startTransaction();
    $this->probe->insert('test')->fields(['name' => 'recovery-row'])->execute();
    $this->checksum->invalidateTags(['probe:a']);
    $this->assertSame(0, $this->counter('probe:a'));
    if ($commit) {
      $transaction->commitOrRelease();
    }
    else {
      $transaction->rollBack();
    }
    if ($destroy) {
      // Deterministically simulate the actual destructor ordering, not a race.
      $this->probe->__destruct();
    }
    unset($transaction);
    $first_count = $this->counter('probe:a');
    $row = $this->connection->select('test', 't')->condition('name', 'recovery-row')->countQuery()->execute()->fetchField();
    $this->assertSame((int) $commit, (int) $row);
    $this->assertSame((int) $commit, $first_count);
    $fresh = $this->backend(new DatabaseCacheTagsChecksum($this->connection));
    $this->assertSame(!$commit, $fresh->get('listing') !== FALSE);
    $this->assertSame($first_count, $this->counter('probe:a'));
    if ($commit && $destroy) {
      $this->assertNotSame($this->probe, Database::getConnection('default', self::KEY));
    }
    else {
      $this->assertSame($this->probe, Database::getConnection('default', self::KEY));
    }
  }

  /**
   * Provides unexpected errors, including a misleading message.
   */
  public static function unexpectedErrors(): array {
    return [
      'unrelated' => ['Unexpected serializer failure'],
      'misleading_message' => ['Unrelated callback argument: null given'],
    ];
  }

  /**
   * Tests that a live client cannot trigger recovery through an error message.
   */
  #[DataProvider('unexpectedErrors')]
  public function testUnexpectedTypeErrorPropagates(string $message): void {
    $error = new \TypeError($message);
    $this->probe->failure = $error;
    $caught = NULL;
    try {
      $this->checksum->invalidateTags(['probe:a']);
    }
    catch (\Throwable $actual) {
      $caught = $actual;
    }
    $this->assertSame($error, $caught, 'Unrelated TypeError must propagate unchanged.');
    $this->assertSame(0, $this->counter('probe:a'));
  }

  /**
   * Provides failures opening a connection or retrying its write.
   */
  public static function recoveryFailures(): array {
    return ['reconnection' => [TRUE], 'retry' => [FALSE]];
  }

  /**
   * Tests that recovery failures propagate unchanged.
   */
  #[DataProvider('recoveryFailures')]
  public function testRecoveryFailurePropagates(bool $opening): void {
    $this->probe->failure = new \TypeError('Simulated lost client connection: null given');
    $this->probe->destroyOnFailure = TRUE;
    $error = new \RuntimeException($opening ? 'Reconnection failed' : 'Retry failed');
    if ($opening) {
      FaultConnection::$openFailure = $error;
    }
    else {
      FaultConnection::$retryFailure = $error;
    }
    $caught = NULL;
    try {
      $this->checksum->invalidateTags(['probe:a']);
    }
    catch (\Throwable $actual) {
      $caught = $actual;
    }
    FaultConnection::$openFailure = FaultConnection::$retryFailure = NULL;
    $this->assertSame($error, $caught, 'The reconnection/retry failure must not be silently accepted.');
    $this->assertSame(0, $this->counter('probe:a'));
  }

  /**
   * Tests that acknowledged tags are not replayed after partial failure.
   */
  public function testPartialInvalidationDoesNotReplayCompletedTags(): void {
    $this->probe->failure = new \TypeError('Simulated lost client connection: null given');
    $this->probe->destroyOnFailure = TRUE;
    $this->probe->failAt = 2;
    $this->checksum->invalidateTags(['probe:a', 'probe:b', 'probe:c']);
    $counts = array_map(fn($tag) => $this->counter($tag), ['probe:a', 'probe:b', 'probe:c']);
    $this->assertSame([1, 1, 1], $counts, 'Retry should complete the remaining tags without incrementing completed tags twice.');
  }

}
