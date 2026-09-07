<?php

namespace Drupal\pgsql;

use Drupal\Core\Database\Database;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\pgsql\Queue\QueueDatabaseFactory;

/**
 * Points the database queue at the PostgreSQL specific implementation.
 */
class PgsqlServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    // This module can be installed while another driver provides the default
    // connection, for example when PostgreSQL only serves a migration source
    // or a secondary connection. Installing it must not change the queue a
    // site running on another database uses, so only take over the service
    // when the default connection is PostgreSQL.
    if ((Database::getConnectionInfo()['default']['driver'] ?? NULL) !== 'pgsql') {
      return;
    }
    if (!$container->hasDefinition('queue.database')) {
      return;
    }
    // Only the class is replaced. The definition keeps its arguments and its
    // 'queue_factory' tag, so the queue stays reachable by its own service
    // name through the queue factory's service locator, and changes other
    // modules made to the definition are preserved.
    $container->getDefinition('queue.database')->setClass(QueueDatabaseFactory::class);
  }

}
