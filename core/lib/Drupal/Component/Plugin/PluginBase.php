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
   * Constructs a \Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin ID for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   */
  public function __construct(protected array $configuration, protected $pluginId, protected $pluginDefinition) {
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

  /**
   * Determines if the plugin is configurable.
   *
   * @return bool
   *   A boolean indicating whether the plugin is configurable.
   *
   * @deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use
   * instanceof to check if the plugin implements
   * \Drupal\Component\Plugin\ConfigurableInterface instead.
   *
   * @see https://www.drupal.org/node/3198285
   */
  public function isConfigurable() {
    @trigger_error(__CLASS__ . "::" . __FUNCTION__ . " is deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use instanceof to check if the plugin implements \Drupal\Component\Plugin\ConfigurableInterface instead. See https://www.drupal.org/node/3198285", E_USER_DEPRECATED);
    return $this instanceof ConfigurableInterface;
  }

}
