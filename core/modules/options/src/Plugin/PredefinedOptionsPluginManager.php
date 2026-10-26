<?php

namespace Drupal\options\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\options\Annotation\PredefinedOptions;

/**
 * Provides the predefined options plugin manager.
 */
class PredefinedOptionsPluginManager extends DefaultPluginManager {

  /**
   * Constructor for PredefinedOptionsManager objects.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/Options', $namespaces, $module_handler, PredefinedOptionsPluginInterface::class, PredefinedOptions::class);

    $this->alterInfo('options_predefined_options_info');
    $this->setCacheBackend($cache_backend, 'options_predefined_options_plugins');
  }

  /**
   * Returns a list of available predefined options plugins.
   *
   * @return array
   *   An array keyed by plugin ID whose values are the plugin labels.
   */
  public function getAvailablePlugins() {
    $options = [];
    foreach ($this->getDefinitions() as $key => $definition) {
      $options[$key] = $definition['label'];
    }
    natcasesort($options);
    return $options;
  }

}
