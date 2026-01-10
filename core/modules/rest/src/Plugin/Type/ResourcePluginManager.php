<?php

namespace Drupal\rest\Plugin\Type;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\Plugin\ResourceInterface;

/**
 * Manages discovery and instantiation of resource plugins.
 *
 * @see \Drupal\rest\Annotation\RestResource
 * @see \Drupal\rest\Plugin\ResourceBase
 * @see \Drupal\rest\Plugin\ResourceInterface
 * @see plugin_api
 */
class ResourcePluginManager extends DefaultPluginManager {

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/rest/resource',
      $namespaces,
      $module_handler,
      ResourceInterface::class,
      RestResource::class,
      'Drupal\rest\Annotation\RestResource',
    );

    $this->setCacheBackend($cache_backend, 'rest_plugins');
    $this->alterInfo('rest_resource');
  }

}
