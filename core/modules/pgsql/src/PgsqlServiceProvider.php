<?php

namespace Drupal\pgsql;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\pgsql\Plugin\views\query\PgsqlCastSql;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the 'pgsql.views.cast_sql' service when views is installed.
 */
class PgsqlServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $modules = $container->getParameter('container.modules');

    if (isset($modules['views'])) {
      $container
        ->register('pgsql.views.cast_sql', PgsqlCastSql::class)
        ->addArgument(new Reference('database'))
        ->setPublic(FALSE);
    }
  }

}
