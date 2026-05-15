<?php

declare(strict_types=1);

namespace Drupal\system;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;

/**
 * Manages discovery and instantiation of Feature Flag plugins.
 */
class FeatureFlagManager extends DefaultPluginManager {

  /**
   * Provides some default values for all Feature Flag plugins.
   *
   * @var array
   */
  protected $defaults = [
    'class' => 'Drupal\system\Plugin\FeatureFlag\FeatureFlag',
  ];

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new FeatureFlagManager.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    // Skip calling the parent constructor, since that assumes annotation-based
    // discovery.
    $this->moduleHandler = $module_handler;

    $this->alterInfo('feature_flag_info');
    $this->setCacheBackend($cache_backend, 'feature_flag_plugins');
  }

  /**
   * {@inheritdoc}
   */
  protected function getType() {
    return 'feature_flag';
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!$this->discovery) {
      $discovery = new YamlDiscovery('feature_flag', $this->moduleHandler->getModuleDirectories());
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($discovery);
    }
    return $this->discovery;
  }

}
