<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection\ContainerStorage;

use Drupal\Component\DependencyInjection\Dumper\OptimizedPhpArrayDumper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ContainerStorageInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Container storage using the array dumper and cache backend.
 *
 * Serializes the container definition as an array using
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
   * @param string $dumperClass
   *   (optional) The class name of the container dumper to use.
   * @param array $runtimeParameters
   *   (optional) Parameter values to set on the container when it is loaded,
   *   keyed by parameter name.
   *
   * @see \Drupal\Core\DependencyInjection\Compiler\RuntimeParametersPass
   */
  public function __construct(
    protected readonly CacheBackendInterface $cache,
    protected readonly Settings $settings,
    protected readonly string $dumperClass = OptimizedPhpArrayDumper::class,
    protected readonly array $runtimeParameters = [],
  ) {}

  /**
   * {@inheritdoc}
   */
  public function load(string $identifier): ?ContainerInterface {
    $cache = $this->cache->get(static::CACHE_PREFIX . $identifier);
    if ($cache) {
      $container_definition = $cache->data;
      $container_definition['parameters'] = $this->runtimeParameters + $container_definition['parameters'];
      $class = $this->settings->get('container_base_class', Container::class);
      return new $class($container_definition);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function dump(ContainerBuilder $container, string $identifier): void {
    $dumper = new $this->dumperClass($container);
    $container_definition = $dumper->getArray();

    $this->cache->set(static::CACHE_PREFIX . $identifier, $container_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(): void {
    $this->cache->deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection(string $identifier): void {
    $this->cache->garbageCollection();
  }

}
