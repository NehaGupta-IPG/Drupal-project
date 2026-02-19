<?php

namespace Drupal\Core\Plugin;

/**
 * Provides a trait for Drupal\Core\PreWarm\PreWarmableInterface.
 *
 * For the vast majority of plugin managers, the ::getDefinitions() method does
 * exactly the right logic for cache prewarming, so this provides a default
 * implementation that uses that.
 *
 * @phpstan-require-implements DiscoveryInterface
 * @phpstan-require-implements PreWarmableInterface
 */
trait PreWarmablePluginManagerTrait {

  /**
   * Implements \Drupal\Core\PreWarm\PreWarmableInterface.
   */
  public function preWarm(): void {
    $this->getDefinitions();
  }

}
