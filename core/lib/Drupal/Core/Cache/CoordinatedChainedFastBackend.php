<?php

namespace Drupal\Core\Cache;

/**
 * Defines a backend with a fast and a consistent backend chain.
 *
 * An extension of the chained fast backend that allows co-ordination of cache
 * invalidations when multiple cache bins are involved. Each chained fast
 * backend must consult the consistent backend once per request to check the
 * last invalidation timestamp. This implementation allows a single lookup per
 * request to determine whether individual persistent backends for each
 * bin need to be consulted.
 *
 * @see \Drupal\Core\Cache\BackendChain
 *
 * @ingroup cache
 */
class CoordinatedChainedFastBackend extends ChainedFastBackend {

  public function __construct(CacheBackendInterface $consistent_backend, CacheBackendInterface $fast_backend, $bin, protected CacheBackendInterface $invalidationBackend) {
    parent::__construct($consistent_backend, $fast_backend, $bin);
  }

  /**
   * Gets the last write timestamp.
   */
  protected function getLastWriteTimestamp(): float {
    if ($this->lastWriteTimestamp === NULL) {
      $cache = $this->invalidationBackend->get(self::LAST_WRITE_TIMESTAMP_PREFIX . $this->bin);
      $this->lastWriteTimestamp = $cache ? $cache->data : 0;
    }
    return $this->lastWriteTimestamp;
  }

  /**
   * Marks the fast cache bin as outdated because of a write.
   */
  protected function markAsOutdated(): void {
    // Clocks on a single server can drift. Multiple servers may have slightly
    // differing opinions about the current time. Given that, do not assume
    // 'now' on this server is always later than our stored timestamp. Add one
    // second to the current time each time we write it to the persistent cache
    // and make sure it is always at least 1ms ahead of the current time. This
    // somewhat protects against clock drift, while also reducing the number of
    // persistent cache writes to one every second if this method is called
    // multiple times during a request. Reads and writes from the fast cache
    // are skipped when this timestamp is in the future, which also helps to
    // avoid write contention on the fast cache.
    $compare = round(microtime(TRUE) + .001, 3);
    if ($compare > $this->getLastWriteTimestamp()) {
      $now = round(microtime(TRUE) + 1, 3);
      $this->lastWriteTimestamp = $now;
      $this->invalidationBackend->set(self::LAST_WRITE_TIMESTAMP_PREFIX . $this->bin, $this->lastWriteTimestamp);
    }
  }

}
