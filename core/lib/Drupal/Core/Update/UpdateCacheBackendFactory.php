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
   * Instantiated update cache bins.
   *
   * @var \Drupal\Core\Update\UpdateBackend[]
   */
  protected $bins = [];

  /**
   * UpdateCacheBackendFactory constructor.
   *
   * @param \Drupal\Core\Cache\CacheFactoryInterface $cacheFactory
   *   The regular runtime cache_factory service.
   */
  public function __construct(protected CacheFactoryInterface $cacheFactory) {
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
