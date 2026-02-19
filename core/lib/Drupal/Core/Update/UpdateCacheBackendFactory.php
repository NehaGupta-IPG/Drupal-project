<?php

namespace Drupal\Core\Update;

use Drupal\Core\Cache\CacheFactoryInterface;

/**
 * Cache factory implementation for use during Drupal database updates.
 *
 * Decorates the regular runtime cache_factory service so that caches use
 * \Drupal\Core\Update\UpdateBackend.
 *
 * @see \Drupal\Core\Update\UpdateServiceProvider::register()
 */
class UpdateCacheBackendFactory implements CacheFactoryInterface {

  /**
   * The regular runtime cache_factory service.
   *
   * @var CacheFactoryInterface
   */
  protected $cacheFactory;

  /**
   * Instantiated update cache bins.
   *
   * @var UpdateBackend[]
   */
  protected $bins = [];

  /**
   * UpdateCacheBackendFactory constructor.
   *
   * @param CacheFactoryInterface $cache_factory
   *   The regular runtime cache_factory service.
   */
  public function __construct(CacheFactoryInterface $cache_factory) {
    $this->cacheFactory = $cache_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    if (!isset($this->bins[$bin])) {
      $this->bins[$bin] = new UpdateBackend($this->cacheFactory->get($bin));
    }
    return $this->bins[$bin];
  }

}
