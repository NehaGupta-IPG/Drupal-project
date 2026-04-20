<?php

namespace Drupal\Core\DependencyInjection\ContainerStorage;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ContainerStorageInterface;
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
   * @param string $baseClass
   *   The container class to instantiate from the cached definition.
   * @param string $dumperClass
   *   The PHP array dumper class.
   * @param string $appRoot
   *   The application root path.
   * @param string $sitePath
   *   The site path.
   */
  public function __construct(
    protected readonly CacheBackendInterface $cache,
    protected readonly string $baseClass,
    protected readonly string $dumperClass,
    protected readonly string $appRoot,
    protected readonly string $sitePath,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function load(string $suffix): ?ContainerInterface {
    $cache = $this->cache->get(static::CACHE_PREFIX . $suffix);
    if ($cache) {
      $container_definition = $cache->data;
      $container_definition['parameters']['app.root'] = $this->appRoot;
      $container_definition['parameters']['site.path'] = $this->sitePath;
      $class = $this->baseClass;
      return new $class($container_definition);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function dump(ContainerBuilder $container, string $suffix): ContainerInterface {
    $dumperClass = $this->dumperClass;
    $dumper = new $dumperClass($container);
    $container_definition = $dumper->getArray();

    try {
      $this->cache->set(static::CACHE_PREFIX . $suffix, $container_definition);
    }
    catch (\Exception) {
      // The cache table may not exist yet (e.g., during install).
    }

    $container_definition['parameters']['app.root'] = $this->appRoot;
    $container_definition['parameters']['site.path'] = $this->sitePath;
    $class = $this->baseClass;
    return new $class($container_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(): void {
    $this->cache->deleteAll();
  }

}
