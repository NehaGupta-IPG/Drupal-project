<?php

namespace Drupal\Component\Plugin;

/**
 * Base class for plugins wishing to support metadata inspection.
 */
abstract class PluginBase implements PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * A string which is used to separate base plugin IDs from the derivative ID.
   */
  const DERIVATIVE_SEPARATOR = ':';

  /**
   * The plugin ID.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * The plugin implementation definition.
   *
   * @var \Drupal\Component\Plugin\Definition\PluginDefinitionInterface|array
   */
  protected $pluginDefinition;

  /**
   * Configuration information passed into the plugin.
   *
   * When using an interface like
   * \Drupal\Component\Plugin\ConfigurableInterface, this is where the
   * configuration should be stored.
   *
   * Plugin configuration is optional, so plugin implementations must provide
   * their own setters and getters.
   *
   * @var array
   */
  protected $configuration;

  /**
   * Constructs a \Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   *   If the plugin is being created by calling
   *   \Drupal\Component\Plugin\Factory\FactoryInterface::createInstance(),
   *   this is the configuration array passed to that method.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin definition returned by the plugin discovery for this plugin
   *   ID. When the plugin is created by a plugin factory, this is either an
   *   array or an object implementing
   *   \Drupal\Component\Plugin\Definition\PluginDefinitionInterface. An array
   *   definition typically includes the 'id', 'class', and 'provider' keys.
   *   For a derivative plugin, 'id' is usually the base plugin ID, not
   *   $plugin_id. The provider is the module that provides the plugin, or
   *   "core" or "component". Other keys depend on the plugin type.
   *
   * @see \Drupal\Component\Plugin\Factory\DefaultFactory::getPluginClass()
   * @see https://www.drupal.org/docs/8/api/plugin-api/plugin-definitions
   * @see plugin_api
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseId() {
    $plugin_id = $this->getPluginId();
    if (strpos($plugin_id, static::DERIVATIVE_SEPARATOR)) {
      [$plugin_id] = explode(static::DERIVATIVE_SEPARATOR, $plugin_id, 2);
    }
    return $plugin_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeId() {
    $plugin_id = $this->getPluginId();
    $derivative_id = NULL;
    if (strpos($plugin_id, static::DERIVATIVE_SEPARATOR)) {
      [, $derivative_id] = explode(static::DERIVATIVE_SEPARATOR, $plugin_id, 2);
    }
    return $derivative_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    return $this->pluginDefinition;
  }

}
