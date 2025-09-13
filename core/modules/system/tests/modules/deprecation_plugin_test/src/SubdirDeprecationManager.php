<?php

namespace Drupal\deprecation_plugin_test;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\LegacySubdirPluginManagerTrait;
use Drupal\deprecation_plugin_test\Attribute\SubdirDeprecation;
use Drupal\deprecation_plugin_test\Plugin\SubdirDeprecationInterface;

/**
 * Manages discovery and instantiation of Subdir Deprecation plugins.
 */
class SubdirDeprecationManager extends DefaultPluginManager {

  use LegacySubdirPluginManagerTrait;

  /**
   * Constructs a new SubdirDeprecationManager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/SubdirDeprecationNew',
      $namespaces,
      $module_handler,
      SubdirDeprecationInterface::class,
      SubdirDeprecation::class,
    );

    $this->setCacheBackend($cache_backend, 'deprecated_subdir_plugins');

    // Set up deprecation.
    $this->legacySubdir = 'Plugin/SubdirDeprecationLegacy';
    $this->deprecationVersion = 'example:12.4.0';
    $this->removedVersion = 'example:13.0.0';
    $this->changeRecordUrl = 'http://example.com/change-record/subdir-deprecation';
  }

}
