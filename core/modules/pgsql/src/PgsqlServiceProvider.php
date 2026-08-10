<?php

declare(strict_types=1);

namespace Drupal\pgsql;

use Drupal\Core\Batch\BatchStorage as CoreBatchStorage;
use Drupal\Core\Cache\DatabaseBackendFactory as CoreDatabaseBackendFactory;
use Drupal\Core\Cache\DatabaseCacheTagsChecksum as CoreDatabaseCacheTagsChecksum;
use Drupal\Core\Config\DatabaseStorage as CoreConfigDatabaseStorage;
use Drupal\Core\Database\Database;
use Drupal\Core\Flood\DatabaseBackend as CoreFloodDatabaseBackend;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\KeyValueStore\KeyValueDatabaseExpirableFactory as CoreKeyValueDatabaseExpirableFactory;
use Drupal\Core\KeyValueStore\KeyValueDatabaseFactory as CoreKeyValueDatabaseFactory;
use Drupal\Core\Lock\DatabaseLockBackend as CoreDatabaseLockBackend;
use Drupal\Core\Lock\PersistentDatabaseLockBackend as CorePersistentDatabaseLockBackend;
use Drupal\Core\Menu\MenuTreeStorage as CoreMenuTreeStorage;
use Drupal\Core\Queue\QueueDatabaseFactory as CoreQueueDatabaseFactory;
use Drupal\Core\Routing\MatcherDumper as CoreMatcherDumper;
use Drupal\Core\Session\SessionHandler as CoreSessionHandler;
use Drupal\Core\Session\UserSessionRepository as CoreUserSessionRepository;
use Drupal\Core\Session\UserSessionRepositoryInterface;
use Drupal\pgsql\Batch\BatchStorage;
use Drupal\pgsql\Cache\DatabaseBackendFactory;
use Drupal\pgsql\Cache\DatabaseCacheTagsChecksum;
use Drupal\pgsql\Config\DatabaseStorage as ConfigDatabaseStorage;
use Drupal\pgsql\Driver\Database\pgsql\Connection;
use Drupal\pgsql\Flood\DatabaseBackend as FloodDatabaseBackend;
use Drupal\pgsql\KeyValueStore\KeyValueDatabaseExpirableFactory;
use Drupal\pgsql\KeyValueStore\KeyValueDatabaseFactory;
use Drupal\pgsql\Lock\DatabaseLockBackend;
use Drupal\pgsql\Lock\PersistentDatabaseLockBackend;
use Drupal\pgsql\Menu\MenuTreeStorage;
use Drupal\pgsql\Queue\QueueDatabaseFactory;
use Drupal\pgsql\Routing\MatcherDumper;
use Drupal\pgsql\Menu\WorkspacesMenuTreeStorage;
use Drupal\pgsql\Session\SessionHandler;
use Drupal\pgsql\Session\UserSessionRepository;
use Drupal\workspaces\WorkspacesMenuTreeStorage as CoreWorkspacesMenuTreeStorage;

/**
 * Replaces database services with PostgreSQL implementations.
 */
class PgsqlServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    // The 'driver' key of the connection info is not reliable: it holds the
    // driver name on a regular site but the driver namespace on test sites.
    // The 'namespace' key is normalized by Database::parseConnectionInfo().
    // Checking the connection class instead of the namespace string also
    // covers drivers that extend the pgsql driver.
    $info = Database::getConnectionInfo();
    $namespace = trim($info['default']['namespace'] ?? '', '\\');
    if (!is_a($namespace . '\\Connection', Connection::class, TRUE)) {
      return;
    }
    // Only replace services that still use the core database implementation,
    // so that sites and tests using another backend are not affected.
    $replacements = [
      'cache.backend.database' => [CoreDatabaseBackendFactory::class, DatabaseBackendFactory::class],
      // Sites and tests may point the cache factory directly at the database
      // cache backend factory instead of using a per-bin backend service.
      'cache_factory' => [CoreDatabaseBackendFactory::class, DatabaseBackendFactory::class],
      'cache_tags.invalidator.checksum' => [CoreDatabaseCacheTagsChecksum::class, DatabaseCacheTagsChecksum::class],
      'batch.storage' => [CoreBatchStorage::class, BatchStorage::class],
      'lock' => [CoreDatabaseLockBackend::class, DatabaseLockBackend::class],
      'lock.persistent' => [CorePersistentDatabaseLockBackend::class, PersistentDatabaseLockBackend::class],
      'menu.tree_storage' => [CoreMenuTreeStorage::class, MenuTreeStorage::class],
      // The workspaces module decorates the menu.tree_storage service with
      // its own subclass of the core menu tree storage, so the replacement
      // above does not cover sites with the workspaces module installed.
      'workspaces.menu.tree_storage' => [CoreWorkspacesMenuTreeStorage::class, WorkspacesMenuTreeStorage::class],
      'queue.database' => [CoreQueueDatabaseFactory::class, QueueDatabaseFactory::class],
      'flood' => [CoreFloodDatabaseBackend::class, FloodDatabaseBackend::class],
      'keyvalue.database' => [CoreKeyValueDatabaseFactory::class, KeyValueDatabaseFactory::class],
      'keyvalue.expirable.database' => [
        CoreKeyValueDatabaseExpirableFactory::class,
        KeyValueDatabaseExpirableFactory::class,
      ],
      'config.storage.active' => [CoreConfigDatabaseStorage::class, ConfigDatabaseStorage::class],
      'config.storage.snapshot' => [CoreConfigDatabaseStorage::class, ConfigDatabaseStorage::class],
      'session_handler.storage' => [CoreSessionHandler::class, SessionHandler::class],
      UserSessionRepositoryInterface::class => [CoreUserSessionRepository::class, UserSessionRepository::class],
      'router.dumper' => [CoreMatcherDumper::class, MatcherDumper::class],
    ];
    foreach ($replacements as $id => [$core_class, $pgsql_class]) {
      if ($container->hasDefinition($id) && ltrim((string) $container->getDefinition($id)->getClass(), '\\') === $core_class) {
        $container->getDefinition($id)->setClass($pgsql_class);
      }
    }
  }

}
