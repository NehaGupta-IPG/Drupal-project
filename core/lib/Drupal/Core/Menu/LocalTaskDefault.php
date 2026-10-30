<?php

namespace Drupal\Core\Menu;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Default object used for LocalTaskPlugins.
 */
class LocalTaskDefault extends PluginBase implements LocalTaskInterface, CacheableDependencyInterface {

  use DependencySerializationTrait;
  use LocalLinkTrait;

  /**
   * TRUE if this plugin is forced active for options attributes.
   *
   * @var bool
   */
  protected $active = FALSE;

  /**
   * Returns the weight of the local task.
   *
   * @return int
   *   The weight of the task. If not defined in the annotation returns 0 by
   *   default or -10 for the root tab.
   */
  public function getWeight() {
    // By default the weight is 0, or -10 for the root tab.
    if (!isset($this->pluginDefinition['weight'])) {
      if ($this->pluginDefinition['base_route'] == $this->pluginDefinition['route_name']) {
        $this->pluginDefinition['weight'] = -10;
      }
      else {
        $this->pluginDefinition['weight'] = 0;
      }
    }
    return (int) $this->pluginDefinition['weight'];
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(RouteMatchInterface $route_match) {
    $options = $this->pluginDefinition['options'];
    if ($this->active) {
      if (empty($options['attributes']['class']) || !in_array('is-active', $options['attributes']['class'])) {
        $options['attributes']['class'][] = 'is-active';
      }
    }
    return (array) $options;
  }

  /**
   * {@inheritdoc}
   */
  public function setActive($active = TRUE) {
    $this->active = $active;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getActive() {
    return $this->active;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    if (!isset($this->pluginDefinition['cache_tags'])) {
      return [];
    }
    return $this->pluginDefinition['cache_tags'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    if (!isset($this->pluginDefinition['cache_contexts'])) {
      return [];
    }
    return $this->pluginDefinition['cache_contexts'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    if (!isset($this->pluginDefinition['cache_max_age'])) {
      return Cache::PERMANENT;
    }
    return $this->pluginDefinition['cache_max_age'];
  }

}
