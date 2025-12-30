<?php

namespace Drupal\Core\Display;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Display\Attribute\DisplayVariant;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages discovery of display variant plugins.
 *
 * @see \Drupal\Core\Display\Attribute\DisplayVariant
 * @see \Drupal\Core\Display\VariantInterface
 * @see \Drupal\Core\Display\VariantBase
 * @see plugin_api
 */
class VariantManager extends DefaultPluginManager {

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/DisplayVariant', $namespaces, $module_handler, VariantInterface::class, DisplayVariant::class, 'Drupal\Core\Display\Annotation\DisplayVariant');

    $this->setCacheBackend($cache_backend, 'variant_plugins');
    $this->alterInfo('display_variant_plugin');
  }

}
