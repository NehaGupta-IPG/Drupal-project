<?php

namespace Drupal\Core\Cache;

use Drupal\Component\Datetime\TimeInterface;

/**
 * Defines a memory cache backend factory.
 */
class MemoryBackendFactory implements CacheFactoryInterface {

  /**
   * Instantiated memory cache bins.
   *
   * @var MemoryBackend[]
   */
  protected $bins = [];

  /**
   * Constructs a MemoryBackendFactory object.
   *
   * @param TimeInterface $time
   *   The time service.
   */
  public function __construct(protected TimeInterface $time) {
  }

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    if (!isset($this->bins[$bin])) {
      $this->bins[$bin] = new MemoryBackend($this->time);
    }
    return $this->bins[$bin];
  }

}
