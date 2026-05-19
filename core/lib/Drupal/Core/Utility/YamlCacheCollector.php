<?php

namespace Drupal\Core\Utility;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheCollector;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Serialization\Yaml;

/**
 * Caches YAML parsing in a cache collector.
 */
class YamlCacheCollector extends CacheCollector {

  public function __construct($cid, CacheBackendInterface $cache, LockBackendInterface $lock, $tags = []) {
    $this->cid = $cid;
    $this->cache = $cache;
    $this->lock = $lock;
    $this->tags = $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function has($key): bool {
    $this->get($key);
    return \array_key_exists($key, $this->storage) && filemtime($key) === $this->storage[$key]['mtime'];
  }

  /**
   * {@inheritdoc}
   */
  public function get($key): array {
    $this->lazyLoadCache();
    if (isset($this->storage[$key]) && file_exists($key)) {
      if ($this->storage[$key]['mtime'] === filemtime($key)) {
        return $this->storage[$key]['decoded'];
      }
    }
    return $this->resolveCacheMiss($key);
  }

  /**
   * {@inheritdoc}
   */
  public function resolveCacheMiss($key): array {
    if (file_exists($key)) {
      $mtime = filemtime($key);
      $this->storage[$key] = [
        'mtime' => $mtime,
        'decoded' => Yaml::decode(file_get_contents($key)) ?? [],
      ];
      $this->persist($key);
      return $this->storage[$key]['decoded'];
    }
    if (isset($this->storage[$key])) {
      $this->delete($key);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function updateCache($lock = TRUE): void {
    // Look for files cached longer than three months ago and remove them from
    // the cache item if they no longer exist.
    $three_months_ago = time() - (90 * 86400);
    foreach ($this->storage as $key => $item) {
      if ($item['mtime'] <= $three_months_ago && !file_exists($key)) {
        $this->delete($key);
      }
    }
  }

}
