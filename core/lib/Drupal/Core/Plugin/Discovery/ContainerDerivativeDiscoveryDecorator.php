<?php

namespace Drupal\Core\Plugin\Discovery;

use Drupal\Component\Plugin\Discovery\DerivativeDiscoveryDecorator;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableDependencyTrait;

/**
 * Injects dependencies into derivers if they use ContainerDeriverInterface.
 *
 * @see \Drupal\Core\Plugin\Discovery\ContainerDeriverInterface
 */
class ContainerDerivativeDiscoveryDecorator extends DerivativeDiscoveryDecorator implements CacheableDependencyInterface {

  use CacheableDependencyTrait;

  /**
   * {@inheritdoc}
   */
  protected function getDeriver($base_plugin_id, $base_definition) {
    if (!isset($this->derivers[$base_plugin_id])) {
      $this->derivers[$base_plugin_id] = FALSE;
      $class = $this->getDeriverClass($base_definition);
      if ($class) {
        // If the deriver provides a factory method, pass the container to it.
        if (is_subclass_of($class, '\Drupal\Core\Plugin\Discovery\ContainerDeriverInterface')) {
          /** @var \Drupal\Core\Plugin\Discovery\ContainerDeriverInterface $class */
          $deriver = $class::create(\Drupal::getContainer(), $base_plugin_id);
        }
        else {
          $deriver = new $class($base_plugin_id);
        }

        if ($deriver instanceof CacheableDependencyInterface) {
          $this->cacheTags = Cache::mergeTags($this->cacheTags, $deriver->getCacheTags());
        }
        $this->derivers[$base_plugin_id] = $deriver;
      }
    }
    return $this->derivers[$base_plugin_id] ?: NULL;
  }

}
