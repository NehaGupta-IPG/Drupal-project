<?php

declare(strict_types=1);

namespace Drupal\sdc_test_plugin_manager\Theme;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Theme\ComponentPluginManager;

/**
 * Provides a test entity type manager.
 */
class TestComponentPluginManager extends ComponentPluginManager {

  /**
   * Sets the discovery for the manager.
   *
   * @param \Drupal\Component\Plugin\Discovery\DiscoveryInterface $discovery
   *   The discovery object.
   */
  public function setDiscovery(DiscoveryInterface $discovery): void {
    $this->discovery = $discovery;
  }

  /**
   * Sets the container for the manager.
   *
   * @param \Drupal\Component\DependencyInjection\ContainerInterface $container
   *   The container object.
   */
  public function setContainer(ContainerInterface $container): void {
    $this->container = $container;
  }

}
