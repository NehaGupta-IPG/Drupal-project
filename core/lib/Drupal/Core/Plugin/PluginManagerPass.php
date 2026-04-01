<?php

namespace Drupal\Core\Plugin;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers plugin managers to the plugin.cache_clearer service.
 */
class PluginManagerPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    foreach ($container->getDefinitions() as $service_id => $definition) {
      if (str_starts_with($service_id, 'plugin.manager.') && !$definition->hasTag('plugin_manager_cache_clear')) {
        if (is_subclass_of($definition->getClass(), '\Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface')) {
          @trigger_error(sprintf('Automatically tagging plugin managers with "plugin_manager_cache_clear" is deprecated in drupal:11.4.0 and will be removed from drupal:13.0.0. Use autoconfiguration or manually tag the "%s" service. See https://www.drupal.org/node/9999999', $service_id), E_USER_DEPRECATED);
          $definition->addTag('plugin_manager_cache_clear');
        }
      }
    }
  }

}
