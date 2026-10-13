<?php

namespace Drupal\Core\Cache;

/**
 * Defines an interface for cache backends that do not use the request time.
 *
 * Cache consumers assume that cache backends use the request time to determine
 * whether a cache item has expired. Cache backends that use a different
 * time, for example the current time in a memory cache used by a long-running
 * process, should implement this interface so that other code can work
 * correctly with them.
 *
 * @ingroup cache
 */
interface CacheTimeInterface {

  /**
   * Returns the time used by the cache backend to determine expiration.
   *
   * @return int
   *   The Unix timestamp used by the cache backend as the current time.
   */
  public function getTime(): int;

}
