<?php

namespace Drupal\Core\DependencyInjection\ContainerStorage;

use Drupal\Component\DependencyInjection\Dumper\OptimizedPhpArrayDumper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ContainerStorageInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Container storage using the PHP array dumper and cache backend.
 *
 * Serializes the container definition as a PHP array using
 * OptimizedPhpArrayDumper and stores it in the cache backend. On load, the
 * definition is retrieved from cache and used to instantiate the container.
 */
class CacheContainerStorage implements ContainerStorageInterface {

  /**
   * The cache prefix used to store container definitions.
   */
  protected const string CACHE_PREFIX = 'service_container:';

  /**
   * Constructs a CacheContainerStorage.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend for storing container definitions.
   * @param \Drupal\Core\Site\Settings $settings
   *   The site settings.
   * @param string|null $dumperClass
   *   (optional) The class name of the container dumper to use.
   */
  public function __construct(
    protected readonly CacheBackendInterface $cache,
    protected readonly Settings $settings,
    protected readonly ?string $dumperClass = OptimizedPhpArrayDumper::class,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function load(string $suffix): ?ContainerInterface {
    $cache = $this->cache->get(static::CACHE_PREFIX . $suffix);
    if ($cache) {
      $container_definition = $cache->data;
      $class = $this->settings->get('container_base_class', Container::class);
      return new $class($container_definition);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function dump(ContainerBuilder $container, string $suffix): ContainerInterface {
    $dumper = new $this->dumperClass($container);
    $container_definition = $dumper->getArray();

    try {
      $this->cache->set(static::CACHE_PREFIX . $suffix, $container_definition);
    }
    catch (\Exception) {
      // The cache table may not exist yet (e.g., during install).
    }

    $class = $this->settings->get('container_base_class', Container::class);
    return new $class($container_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(): void {
    $this->cache->deleteAll();
  }

}
