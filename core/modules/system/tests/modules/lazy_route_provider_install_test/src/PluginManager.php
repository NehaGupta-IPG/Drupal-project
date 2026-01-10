<?php

declare(strict_types=1);

namespace Drupal\lazy_route_provider_install_test;

use Drupal\Component\Plugin\Attribute\PluginID;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Url;

/**
 * Plugin manager for testing the lazy route provider during module install.
 */
class PluginManager extends DefaultPluginManager {

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, UrlGeneratorInterface $url_generator) {
    // Generate a URL during construction to prove that URL generation works. If
    // the route was missing an exception would be thrown. This also forces the
    // route provider to be initialized very early during a module install.
    \Drupal::state()->set(__CLASS__, Url::fromRoute('system.admin')->toString());
    parent::__construct('Plugin/LazyRouteProviderInstallTest', $namespaces, $module_handler, NULL, PluginID::class);
  }

}
