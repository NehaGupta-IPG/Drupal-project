<?php

namespace Drupal\mongodb;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers services in the container.
 */
class MongodbServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');

    // Add the entity query override only if workspaces module is enabled.
    if (isset($modules['workspaces'])) {
      $container->register('mongodb.workspaces.entity.query.sql', 'Drupal\mongodb\modules\workspaces\EntityQuery\QueryFactory')
        ->addArgument(new Reference(('database')))
        ->addArgument(new Reference(('workspaces.manager')))
        ->addArgument(new Reference(('workspaces.information')))
        ->setPublic(FALSE)
        ->setDecoratedService('mongodb.entity.query.sql', NULL, 50);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');

    if (isset($modules['file'])) {
      $definition = $container->getDefinition('file.usage');
      $definition->setClass('Drupal\mongodb\modules\file\DatabaseFileUsageBackend')
        ->addArgument(new Reference('database'))
        ->addArgument('file_usage')
        ->addArgument(new Reference('config.factory'));
    }

    if (isset($modules['dblog'])) {
      $definition = $container->getDefinition('logger.dblog');
      $definition->setClass('Drupal\mongodb\modules\dblog\DbLog')
        ->addArgument(new Reference('database'))
        ->addArgument(new Reference('logger.log_message_parser'));
    }

    if (isset($modules['search'])) {
      $definition = $container->getDefinition('search.index');
      $definition->setClass('Drupal\mongodb\modules\search\SearchIndex')
        ->addArgument(new Reference('config.factory'))
        ->addArgument(new Reference('database'))
        ->addArgument(new Reference('database.replica'))
        ->addArgument(new Reference('cache_tags.invalidator'))
        ->addArgument(new Reference('search.text_processor'));
    }
  }

}
