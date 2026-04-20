<?php

namespace Drupal\Core\DependencyInjection\ContainerStorage;

use Drupal\Component\DependencyInjection\Dumper\OptimizedPhpArrayDumper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\DependencyInjection\ContainerStorageInterface;
use Drupal\Core\DependencyInjection\DumpedContainer;
use Drupal\Core\PhpStorage\PhpStorageFactory;
use Drupal\Core\Site\Settings;

/**
 * Factory for creating the container storage service.
 *
 * Determines which ContainerStorageInterface implementation to use based on
 * settings. Sites can explicitly set 'container_storage_class' in settings.php
 * to use a custom implementation (e.g., for shipping pre-built containers in
 * Git). Otherwise, the implementation is auto-detected from
 * 'container_base_class'.
 */
class ContainerStorageFactory {

  /**
   * Creates the container storage service.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend from the bootstrap container.
   * @param string $appRoot
   *   The application root path.
   * @param string $sitePath
   *   The site path.
   *
   * @return \Drupal\Core\DependencyInjection\ContainerStorageInterface
   *   The container storage service.
   */
  public static function create(CacheBackendInterface $cache, string $appRoot, string $sitePath): ContainerStorageInterface {
    // Allow explicit override via settings.
    $storageClass = Settings::get('container_storage_class');
    if ($storageClass) {
      return new $storageClass($cache, $appRoot, $sitePath);
    }

    $baseClass = Settings::get('container_base_class', Container::class);

    if (is_a($baseClass, DumpedContainer::class, TRUE)) {
      try {
        $phpStorage = PhpStorageFactory::get('service_container');
      }
      catch (\RuntimeException) {
        // PhpStorageFactory requires hash_salt which may not be available
        // yet (e.g., during install or in minimal test environments).
        return new NullContainerStorage();
      }
      return new PhpDumperContainerStorage($cache, $phpStorage, $baseClass);
    }

    return new CacheContainerStorage(
      $cache,
      $baseClass,
      Settings::get('container_dumper_class', OptimizedPhpArrayDumper::class),
      $appRoot,
      $sitePath,
    );
  }

}
