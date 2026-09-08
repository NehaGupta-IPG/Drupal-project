<?php

declare(strict_types=1);

namespace Drupal\pgsql;

use Drupal\Core\Database\Database;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\Lock\DatabaseLockBackend as CoreDatabaseLockBackend;
use Drupal\Core\Lock\PersistentDatabaseLockBackend as CorePersistentDatabaseLockBackend;
use Drupal\pgsql\Driver\Database\pgsql\Connection;
use Drupal\pgsql\Lock\DatabaseLockBackend;
use Drupal\pgsql\Lock\PersistentDatabaseLockBackend;

/**
 * Replaces the database lock backends with PostgreSQL implementations.
 *
 * @see \Drupal\pgsql\Lock\PgsqlLockBackendTrait
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
    // covers drivers that extend the PostgreSQL driver.
    $namespace = trim(Database::getConnectionInfo()['default']['namespace'] ?? '', '\\');
    if (!is_a($namespace . '\\Connection', Connection::class, TRUE)) {
      return;
    }
    // Only the core database lock backends are replaced, so that a site or a
    // test using another lock backend is not affected.
    $replacements = [
      'lock' => [CoreDatabaseLockBackend::class, DatabaseLockBackend::class],
      'lock.persistent' => [CorePersistentDatabaseLockBackend::class, PersistentDatabaseLockBackend::class],
    ];
    foreach ($replacements as $id => [$core_class, $pgsql_class]) {
      if ($container->hasDefinition($id) && ltrim((string) $container->getDefinition($id)->getClass(), '\\') === $core_class) {
        $container->getDefinition($id)->setClass($pgsql_class);
      }
    }
  }

}
