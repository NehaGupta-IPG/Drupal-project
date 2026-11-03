<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\PhpSerialize;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Batch\BatchStorage;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Cache\DatabaseBackend as CacheDatabaseBackend;
use Drupal\Core\Cache\DatabaseCacheTagsChecksum;
use Drupal\Core\Config\DatabaseStorage as ConfigDatabaseStorage;
use Drupal\Core\Flood\DatabaseBackend as FloodDatabaseBackend;
use Drupal\Core\KeyValueStore\DatabaseStorage as KeyValueDatabaseStorage;
use Drupal\Core\Lock\DatabaseLockBackend;
use Drupal\Core\Menu\MenuTreeStorage;
use Drupal\Core\Queue\DatabaseQueue;
use Drupal\Core\Routing\MatcherDumper;
use Drupal\Core\Session\SessionHandler;
use Drupal\Core\State\StateInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Tests the deprecated lazy table creation helpers of database backends.
 */
#[Group('Database')]
#[Group('legacy')]
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
class LegacyLazyTableCreationTest extends DatabaseTestBase {

  /**
   * Tests that the deprecated helpers still create their table.
   */
  #[TestWith([BatchStorage::class, 'ensureTableExists', 'batch'])]
  #[TestWith([CacheDatabaseBackend::class, 'ensureBinExists', 'cache_legacy'])]
  #[TestWith([DatabaseCacheTagsChecksum::class, 'ensureTableExists', 'cachetags'])]
  #[TestWith([ConfigDatabaseStorage::class, 'ensureTableExists', 'legacy_config'])]
  #[TestWith([FloodDatabaseBackend::class, 'ensureTableExists', 'flood'])]
  #[TestWith([KeyValueDatabaseStorage::class, 'ensureTableExists', 'legacy_key_value'])]
  #[TestWith([DatabaseLockBackend::class, 'ensureTableExists', 'semaphore'])]
  #[TestWith([MenuTreeStorage::class, 'ensureTableExists', 'legacy_menu_tree'])]
  #[TestWith([DatabaseQueue::class, 'ensureTableExists', 'queue'])]
  #[TestWith([MatcherDumper::class, 'ensureTableExists', 'legacy_router'])]
  #[TestWith([SessionHandler::class, 'ensureTableExists', 'sessions'])]
  public function testEnsureTableExists(string $class, string $method, string $table): void {
    $backend = $this->createBackend($class);
    $this->expectUserDeprecationMessage($class . '::' . $method . '() is deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. Use \Drupal\Core\Database\Connection::executeAndCreateTableIfNeeded() instead. See https://www.drupal.org/node/3489185');
    (new \ReflectionMethod($backend, $method))->invoke($backend);
    $this->assertTrue($this->connection->schema()->tableExists($table));
  }

  /**
   * Creates a backend that stores its data in the test database.
   *
   * @param class-string $class
   *   The backend class.
   *
   * @return object
   *   The backend.
   */
  private function createBackend(string $class): object {
    $time = $this->createStub(TimeInterface::class);
    return match ($class) {
      BatchStorage::class => new BatchStorage($this->connection, $this->createStub(SessionInterface::class), $this->createStub(CsrfTokenGenerator::class), $time),
      CacheDatabaseBackend::class => new CacheDatabaseBackend($this->connection, $this->createStub(CacheTagsChecksumInterface::class), 'legacy', new PhpSerialize(), $time),
      DatabaseCacheTagsChecksum::class => new DatabaseCacheTagsChecksum($this->connection),
      ConfigDatabaseStorage::class => new ConfigDatabaseStorage($this->connection, 'legacy_config'),
      FloodDatabaseBackend::class => new FloodDatabaseBackend($this->connection, new RequestStack(), $time),
      KeyValueDatabaseStorage::class => new KeyValueDatabaseStorage('legacy', new PhpSerialize(), $this->connection, 'legacy_key_value'),
      DatabaseLockBackend::class => new DatabaseLockBackend($this->connection),
      MenuTreeStorage::class => new MenuTreeStorage($this->connection, $this->createStub(CacheBackendInterface::class), $this->createStub(CacheTagsInvalidatorInterface::class), 'legacy_menu_tree'),
      DatabaseQueue::class => new DatabaseQueue('legacy', $this->connection),
      MatcherDumper::class => new MatcherDumper($this->connection, $this->createStub(StateInterface::class), $this->createStub(LoggerInterface::class), 'legacy_router'),
      SessionHandler::class => new SessionHandler(new RequestStack(), $this->connection, $time),
    };
  }

}
