<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Kernel\pgsql;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Flood\DatabaseBackend as CoreFloodDatabaseBackend;
use Drupal\Core\Lock\DatabaseLockBackend as CoreDatabaseLockBackend;
use Drupal\KernelTests\Core\Database\DriverSpecificKernelTestBase;
use Drupal\pgsql\Cache\DatabaseBackend;
use Drupal\pgsql\Cache\DatabaseCacheTagsChecksum;
use Drupal\pgsql\Config\DatabaseStorage as ConfigDatabaseStorage;
use Drupal\pgsql\Flood\DatabaseBackend as FloodDatabaseBackend;
use Drupal\pgsql\KeyValueStore\DatabaseStorage as KeyValueDatabaseStorage;
use Drupal\pgsql\KeyValueStore\DatabaseStorageExpirable as KeyValueDatabaseStorageExpirable;
use Drupal\pgsql\Lock\DatabaseLockBackend;
use Drupal\pgsql\Lock\PersistentDatabaseLockBackend;
use Drupal\pgsql\Menu\MenuTreeStorage;
use Drupal\pgsql\Menu\WorkspacesMenuTreeStorage;
use Drupal\pgsql\ProxyClass\Batch\BatchStorage as BatchStorageProxy;
use Drupal\pgsql\ProxyClass\Routing\MatcherDumper as MatcherDumperProxy;
use Drupal\pgsql\Queue\DatabaseQueue;
use Drupal\Core\Session\UserSessionRepositoryInterface;
use Drupal\pgsql\Session\SessionHandler;
use Drupal\pgsql\Session\UserSessionRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests the PostgreSQL replacements of database services.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class PgsqlServiceProviderTest extends DriverSpecificKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    // KernelTestBase replaces the lock and flood services with memory
    // backends. Restore the database-backed definitions so the pgsql
    // replacements apply.
    $container->register('lock', CoreDatabaseLockBackend::class)
      ->addArgument(new Reference('database'));
    $container->register('flood', CoreFloodDatabaseBackend::class)
      ->addArgument(new Reference('database'))
      ->addArgument(new Reference('request_stack'))
      ->addArgument(new Reference('datetime.time'));
  }

  /**
   * Tests that the database cache services use the pgsql implementations.
   */
  public function testDatabaseCacheServicesAreReplaced(): void {
    $this->assertInstanceOf(DatabaseCacheTagsChecksum::class, $this->container->get('cache_tags.invalidator.checksum'));
    $this->assertInstanceOf(DatabaseBackend::class, $this->container->get('cache.backend.database')->get('default'));
    // The lazy batch.storage service is wrapped in a proxy; the pgsql proxy
    // class proves the service definition uses the pgsql implementation.
    $this->assertInstanceOf(BatchStorageProxy::class, $this->container->get('batch.storage'));
    $this->assertInstanceOf(DatabaseLockBackend::class, $this->container->get('lock'));
    $this->assertInstanceOf(PersistentDatabaseLockBackend::class, $this->container->get('lock.persistent'));
    $this->assertInstanceOf(DatabaseQueue::class, $this->container->get('queue.database')->get('pgsql_test_queue'));
    $this->assertInstanceOf(FloodDatabaseBackend::class, $this->container->get('flood'));
    $this->assertInstanceOf(KeyValueDatabaseStorage::class, $this->container->get('keyvalue.database')->get('pgsql_test'));
    $this->assertInstanceOf(KeyValueDatabaseStorageExpirable::class, $this->container->get('keyvalue.expirable.database')->get('pgsql_test'));
    $this->assertInstanceOf(ConfigDatabaseStorage::class, $this->container->get('config.storage.snapshot'));
    // The config.storage.active service is private; check it through the
    // cached config storage that decorates it.
    $config_storage = $this->container->get('config.storage');
    $active = (new \ReflectionObject($config_storage))->getProperty('storage')->getValue($config_storage);
    $this->assertInstanceOf(ConfigDatabaseStorage::class, $active);
    $this->assertInstanceOf(SessionHandler::class, $this->container->get('session_handler.storage'));
    $this->assertInstanceOf(UserSessionRepository::class, $this->container->get(UserSessionRepositoryInterface::class));
    // The lazy router.dumper service is wrapped in a proxy; the pgsql proxy
    // class proves the service definition uses the pgsql implementation.
    $this->assertInstanceOf(MatcherDumperProxy::class, $this->container->get('router.dumper'));
    // The menu.tree_storage service is private; check it through the menu
    // link plugin manager.
    $menu_link_manager = $this->container->get('plugin.manager.menu.link');
    $tree_storage = (new \ReflectionObject($menu_link_manager))->getProperty('treeStorage')->getValue($menu_link_manager);
    $this->assertInstanceOf(MenuTreeStorage::class, $tree_storage);
  }

  /**
   * Tests that the workspaces menu tree storage uses the pgsql class.
   */
  public function testWorkspacesMenuTreeStorageIsReplaced(): void {
    $this->enableModules(['user', 'workspaces']);
    $menu_link_manager = $this->container->get('plugin.manager.menu.link');
    $tree_storage = (new \ReflectionObject($menu_link_manager))->getProperty('treeStorage')->getValue($menu_link_manager);
    $this->assertInstanceOf(WorkspacesMenuTreeStorage::class, $tree_storage);
  }

  /**
   * Tests that a missing cache bin is created before use in a transaction.
   */
  public function testBinCreatedOnDemandInsideTransaction(): void {
    $backend = $this->container->get('cache.backend.database')->get('pgsql_on_demand');
    $this->assertFalse($this->connection->schema()->tableExists('cache_pgsql_on_demand'));

    $transaction = $this->connection->startTransaction();
    $backend->set('key', 'value');
    $this->assertSame('value', $backend->get('key')->data);

    // The transaction was not aborted by the missing table and commits.
    $this->connection->insert('cache_pgsql_on_demand')->fields([
      'cid' => 'other',
      'data' => 'x',
      'expire' => -1,
      'created' => 0,
      'checksum' => '',
    ])->execute();
    $transaction->commitOrRelease();
    $this->assertSame('value', $backend->get('key')->data);
  }

}
