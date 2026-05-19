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
  public function has($key): bool{
    $this->get($key);
    return \array_key_exists($key, $this->storage) && filemtime($key) === $this->storage[$key]['mtime'];
  }

  /**
   * {@inheritdoc}
   */
  public function get($key): array {
    $this->lazyLoadCache();
    if (isset($this->storage[$key])) {
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
    $mtime = filemtime($key);
    $this->storage[$key] = [
      'mtime' => $mtime,
      'decoded' => Yaml::decode(file_get_contents($key)) ?? [],
    ];
    $this->persist($key);
    return $this->storage[$key]['decoded'];
  }

}
