<?php

namespace Drupal\Core\Cache;

use Drupal\Component\Datetime\TimeInterface;

/**
 * Defines the memory counter backend factory.
 */
class MemoryCounterBackendFactory implements CacheFactoryInterface {

  /**
   * Instantiated memory cache bins.
   *
   * @var \Drupal\Core\Cache\MemoryBackend[]
   */
  protected $bins = [];

  public function __construct(protected TimeInterface $time) {
  }

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    if (!isset($this->bins[$bin])) {
      $this->bins[$bin] = new MemoryCounterBackend($this->time);
    }
    return $this->bins[$bin];
  }

}
