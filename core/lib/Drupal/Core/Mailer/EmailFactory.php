<?php

namespace Drupal\Core\Mailer;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\Factory\ContainerFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Email plugin factory.
 *
 * Symfony email instances are created as Drupal plugins, but the default plugin
 * constructor is not compatible.
 */
class EmailFactory extends ContainerFactory {

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    $plugin_definition = $this->discovery->getDefinition($plugin_id);
    $plugin_class = static::getPluginClass($plugin_id, $plugin_definition, $this->interface);

    // If the plugin provides a factory method, pass the container to it.
    if (is_subclass_of($plugin_class, ContainerFactoryPluginInterface::class)) {
      return $plugin_class::create(\Drupal::getContainer(), $configuration, $plugin_id, $plugin_definition);
    }

    // Drupal plugins are subtypes of PluginBase and share the constructor
    // signature. The Symfony Email class doesn't take any constructor arguments
    // and therefore needs to be instantiated without them.
    if (!is_subclass_of($plugin_class, PluginBase::class, TRUE)) {
      return new $plugin_class();
    }

    // Otherwise, create the plugin as normal.
    return new $plugin_class($configuration, $plugin_id, $plugin_definition);
  }

}
