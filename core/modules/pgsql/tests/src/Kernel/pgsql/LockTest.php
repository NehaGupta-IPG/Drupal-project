<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Kernel\pgsql;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Lock\DatabaseLockBackend as CoreDatabaseLockBackend;
use Drupal\KernelTests\Core\Database\DriverSpecificKernelTestBase;
use Drupal\pgsql\Lock\DatabaseLockBackend;
use Drupal\pgsql\Lock\PersistentDatabaseLockBackend;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests the PostgreSQL database lock backend.
 */
#[Group('Lock')]
#[RunTestsInSeparateProcesses]
class LockTest extends DriverSpecificKernelTestBase {

  /**
   * The lock backend to test.
   */
  protected DatabaseLockBackend $lock;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    // KernelTestBase replaces the lock service with a null backend. Restore
    // the database-backed definition, so that the replacement by
    // \Drupal\pgsql\PgsqlServiceProvider can be tested.
    $container->register('lock', CoreDatabaseLockBackend::class)
      ->addArgument(new Reference('database'));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->lock = new DatabaseLockBackend($this->connection);
    $this->connection->schema()->createTable('semaphore', $this->lock->schemaDefinition());
  }

  /**
   * Tests that the lock services use the PostgreSQL implementations.
   */
  public function testLockServicesAreReplaced(): void {
    $this->assertInstanceOf(DatabaseLockBackend::class, $this->container->get('lock'));
    $this->assertInstanceOf(PersistentDatabaseLockBackend::class, $this->container->get('lock.persistent'));
  }

  /**
   * Tests acquiring, extending and releasing a lock.
   */
  public function testLockAcquireAndRelease(): void {
    $this->assertTrue($this->lock->acquire('lock_a'));
    $this->assertFalse($this->lock->lockMayBeAvailable('lock_a'));

    // Acquiring a lock this request holds extends its expiration.
    $expire = $this->lockExpiration('lock_a');
    $this->assertTrue($this->lock->acquire('lock_a', 60.0));
    $this->assertGreaterThan($expire, $this->lockExpiration('lock_a'));

    $this->lock->release('lock_a');
    $this->assertTrue($this->lock->lockMayBeAvailable('lock_a'));
  }

  /**
   * Tests that a lock held by another request is not acquired.
   */
  public function testAcquireLockHeldByOtherRequest(): void {
    $this->holdLock('lock_b', 300.0);
    $this->assertFalse($this->lock->acquire('lock_b'));
    $this->assertSame('other-request', $this->lockValue('lock_b'));
  }

  /**
   * Tests that an expired lock of another request is taken over.
   */
  public function testAcquireExpiredLockOfOtherRequest(): void {
    $this->holdLock('lock_c', -1.0);
    $this->assertTrue($this->lock->acquire('lock_c'));
    $this->assertSame($this->lock->getLockId(), $this->lockValue('lock_c'));
  }

  /**
   * Tests acquiring a lock held by another request inside a transaction.
   *
   * The insert query of the core lock backend fails on the primary key of the
   * semaphore table when another request holds the lock. On PostgreSQL that
   * failed statement aborts the whole enclosing transaction, so the upsert
   * query of the PostgreSQL lock backend must report the unavailable lock
   * without failing a statement.
   */
  public function testAcquireLockHeldByOtherRequestInTransaction(): void {
    $this->holdLock('lock_d', 300.0);

    $transaction = $this->connection->startTransaction();
    $this->holdLock('canary', 300.0);
    $this->assertFalse($this->lock->acquire('lock_d'));

    // The transaction is not aborted, so it still accepts statements and the
    // work done before the unavailable lock is committed.
    $this->assertSame('other-request', $this->lockValue('lock_d'));
    $transaction->commitOrRelease();
    $this->assertSame('other-request', $this->lockValue('canary'));
  }

  /**
   * Inserts the semaphore row of a lock held by another request.
   *
   * @param string $name
   *   The name of the lock.
   * @param float $timeout
   *   The number of seconds until the lock expires. A negative number holds an
   *   expired lock.
   */
  protected function holdLock(string $name, float $timeout): void {
    $this->connection->insert('semaphore')
      ->fields([
        'name' => $name,
        'value' => 'other-request',
        'expire' => microtime(TRUE) + $timeout,
      ])
      ->execute();
  }

  /**
   * Returns the lock ID stored in the semaphore row of a lock.
   *
   * @param string $name
   *   The name of the lock.
   *
   * @return string|null
   *   The lock ID, or NULL if the lock is not held.
   */
  protected function lockValue(string $name): ?string {
    $value = $this->connection->select('semaphore', 's')
      ->fields('s', ['value'])
      ->condition('name', $name)
      ->execute()
      ->fetchField();
    return $value === FALSE ? NULL : $value;
  }

  /**
   * Returns the expiration stored in the semaphore row of a lock.
   *
   * @param string $name
   *   The name of the lock.
   *
   * @return float
   *   The expiration of the lock.
   */
  protected function lockExpiration(string $name): float {
    return (float) $this->connection->select('semaphore', 's')
      ->fields('s', ['expire'])
      ->condition('name', $name)
      ->execute()
      ->fetchField();
  }

}
