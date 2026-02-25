<?php

namespace Drupal\Core\Plugin\Factory;

use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;

/**
 * Plugin factory which passes a container to a create method.
 */
class ContainerFactory extends DefaultFactory {

  use AutowiredInstanceTrait;

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    $plugin_definition = $this->discovery->getDefinition($plugin_id);
    $plugin_class = static::getPluginClass($plugin_id, $plugin_definition, $this->interface);

    // If the plugin provides a factory method, pass the container to it.
    if (is_subclass_of($plugin_class, 'Drupal\Core\Plugin\ContainerFactoryPluginInterface')) {
      $container = \Drupal::getContainer();
      $plugin = $plugin_class::create($container, $configuration, $plugin_id, $plugin_definition);
      self::invokeRequiredMethods($plugin, $container);
      return $plugin;
    }

    // Otherwise, create the plugin directly.
    return new $plugin_class($configuration, $plugin_id, $plugin_definition);
  }

}
