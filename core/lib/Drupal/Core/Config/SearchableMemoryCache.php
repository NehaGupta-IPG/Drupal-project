<?php

declare(strict_types=1);

namespace Drupal\Core\Config;

use Drupal\Core\Cache\MemoryCache\MemoryCache;

/**
 * Defines a memory cache implementation that can search for keys.
 *
 * Stores cache items in memory using a PHP array.
 *
 * @ingroup cache
 */
class SearchableMemoryCache extends MemoryCache {

  /**
   * Gets all the cache keys that match the provided config name.
   *
   * @param string $name
   *   The name of the configuration object.
   *
   * @return array
   *   An array of cache keys that match the provided config name.
   */
  public function getConfigCacheKeys($name): array {
    return array_filter(array_keys($this->cache), function ($key) use ($name) {
      // Return TRUE if the key is the name or starts with the configuration
      // name plus the delimiter.
      return $key === $name || strpos($key, $name . ':') === 0;
    });
  }

}
