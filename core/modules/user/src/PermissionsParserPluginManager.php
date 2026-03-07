<?php

declare(strict_types = 1);

namespace Drupal\user;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\user\Attribute\PermissionsParser;

/**
 * Manages discovery of permissions parser plugins.
 *
 * For instance, one plugin might handle the 'permission_callbacks' key
 * to be found in *.permissions.yml files.
 *
 * @see plugin_api
 */
class PermissionsParserPluginManager extends DefaultPluginManager {

  /**
   * Constructs a new PermissionsParserPluginManager.
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
    parent::__construct('Plugin/PermissionsParser', $namespaces, $module_handler, PermissionsParserInterface::class, PermissionsParser::class);

    $this->setCacheBackend($cache_backend, 'user_permissions_parser_plugins');
    $this->alterInfo('user_permissions_parser');
  }

}
