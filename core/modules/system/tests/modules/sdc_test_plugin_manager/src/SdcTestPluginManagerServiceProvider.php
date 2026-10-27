<?php

declare(strict_types=1);

namespace Drupal\sdc_test_plugin_manager;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

use Drupal\sdc_test_plugin_manager\Theme\TestComponentPluginManager;

/**
 * Modifies the component plugin manager service.
 */
class SdcTestPluginManagerServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    // Overrides plugin.manager.sdc class to facilitate testing.
    if ($container->hasDefinition('plugin.manager.sdc')) {
      $definition = $container->getDefinition('plugin.manager.sdc');
      $definition->setClass(TestComponentPluginManager::class);
    }
  }

}
