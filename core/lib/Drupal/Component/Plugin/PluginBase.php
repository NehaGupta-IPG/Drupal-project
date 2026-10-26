<?php

namespace Drupal\Component\Plugin;

/**
 * Base class for plugins wishing to support metadata inspection.
 */
abstract class PluginBase implements PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * A string which is used to separate base plugin IDs from the derivative ID.
   *
   * @final
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
   *   The plugin implementation definition.
   *
   * @see https://www.drupal.org/docs/8/api/plugin-api/plugin-definitions
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
    if ($parts = $this->splitPluginIdByConstant($plugin_id)) {
      return $parts[0];
    }
    return explode(static::getSeparator($this->pluginDefinition), $plugin_id, 2)[0];
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeId() {
    $plugin_id = $this->getPluginId();
    if ($parts = $this->splitPluginIdByConstant($plugin_id)) {
      return $parts[1];
    }
    return explode(static::getSeparator($this->pluginDefinition), $plugin_id, 2)[1];
  }

  /**
   * Gets the separator to split plugin ids from.
   *
   * @param array $plugin_definition
   *   The plugin definition to get the derivative_separator from.
   *
   * @return string
   *   The separator string for plugin ids.
   */
  public static function getSeparator(array $plugin_definition): string {
    return $plugin_definition['derivative_separator'] ?? self::DERIVATIVE_SEPARATOR;
  }

  /**
   * Splits the plugin id by the separator constant.
   *
   * @param string $plugin_id
   *   The plugin id to separate.
   */
  protected function splitPluginIdByConstant(string $plugin_id): array {
    if (static::DERIVATIVE_SEPARATOR !== self::DERIVATIVE_SEPARATOR) {
      @trigger_error('Overriding the DERIVATIVE_SEPARATOR constant is deprecated in drupal:12.0.1 and removed in 13.0.0. There is no replacement. See https://www.drupal.org/node/3633174.', E_USER_DEPRECATED);
      if (strpos($plugin_id, static::DERIVATIVE_SEPARATOR)) {
        return explode(static::DERIVATIVE_SEPARATOR, $plugin_id, 2);
      }
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    return $this->pluginDefinition;
  }

}
