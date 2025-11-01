<?php

namespace Drupal\Core\Config;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Defines the cached storage.
 *
 * The class gets another storage and a cache backend injected. It reads from
 * the cache and delegates the read to the storage on a cache miss. It also
 * handles cache invalidation.
 */
class CachedStorage implements StorageInterface, StorageCacheInterface {
  use DependencySerializationTrait;

  /**
   * List of listAll() prefixes with their results.
   *
   * @var array
   */
  protected $findByPrefixCache = [];

  public function __construct(
    protected \Closure $storageClosure,
    protected CacheBackendInterface $cache,
    protected $collection = NULL,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function exists($name) {
    // The cache would read in the entire data (instead of only checking whether
    // any data exists), and on a potential cache miss, an additional storage
    // lookup would have to happen, so check the storage directly.
    return ($this->storageClosure)()->exists($name);
  }

  /**
   * {@inheritdoc}
   */
  public function read($name) {
    $cache_key = $this->getCacheKey($name);
    if ($cache = $this->cache->get($cache_key)) {
      // The cache contains either the cached configuration data or FALSE
      // if the configuration file does not exist.
      return $cache->data;
    }
    // Read from the storage on a cache miss and cache the data. Also cache
    // information about missing configuration objects.
    $data = ($this->storageClosure)()->read($name);
    $this->cache->set($cache_key, $data);
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function readMultiple(array $names) {
    $data_to_return = [];

    $cache_keys_map = $this->getCacheKeys($names);
    $cache_keys = array_values($cache_keys_map);
    $cached_list = $this->cache->getMultiple($cache_keys);

    if (!empty($cache_keys)) {
      // $cache_keys_map contains the full $name => $cache_key map, while
      // $cache_keys contains just the $cache_key values that weren't found in
      // the cache.
      // @see \Drupal\Core\Cache\CacheBackendInterface::getMultiple()
      $names_to_get = array_keys(array_intersect($cache_keys_map, $cache_keys));
      $list = ($this->storageClosure)()->readMultiple($names_to_get);
      // Cache configuration objects that were loaded from the storage, cache
      // missing configuration objects as an explicit FALSE.
      $items = [];
      foreach ($names_to_get as $name) {
        $data = $list[$name] ?? FALSE;
        $data_to_return[$name] = $data;
        $items[$cache_keys_map[$name]] = ['data' => $data];
      }

      $this->cache->setMultiple($items);
    }

    // Add the configuration objects from the cache to the list.
    $cache_keys_inverse_map = array_flip($cache_keys_map);
    foreach ($cached_list as $cache_key => $cache) {
      $name = $cache_keys_inverse_map[$cache_key];
      $data_to_return[$name] = $cache->data;
    }

    // Ensure that only existing configuration objects are returned, filter out
    // cached information about missing objects.
    return array_filter($data_to_return);
  }

  /**
   * {@inheritdoc}
   */
  public function write($name, array $data) {
    if (($this->storageClosure)()->write($name, $data)) {
      // While not all written data is read back, setting the cache instead of
      // just deleting it avoids cache rebuild stampedes.
      $this->cache->set($this->getCacheKey($name), $data);
      $this->findByPrefixCache = [];
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function delete($name) {
    // If the cache was the first to be deleted, another process might start
    // rebuilding the cache before the storage is gone.
    if (($this->storageClosure)()->delete($name)) {
      $this->cache->delete($this->getCacheKey($name));
      $this->findByPrefixCache = [];
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function rename($name, $new_name) {
    // If the cache was the first to be deleted, another process might start
    // rebuilding the cache before the storage is renamed.
    if (($this->storageClosure)()->rename($name, $new_name)) {
      $this->cache->delete($this->getCacheKey($name));
      $this->cache->delete($this->getCacheKey($new_name));
      $this->findByPrefixCache = [];
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function encode($data) {
    return ($this->storageClosure)()->encode($data);
  }

  /**
   * {@inheritdoc}
   */
  public function decode($raw) {
    return ($this->storageClosure)()->decode($raw);
  }

  /**
   * {@inheritdoc}
   */
  public function listAll($prefix = '') {
    // Do not cache when a prefix is not provided.
    if ($prefix) {
      return $this->findByPrefix($prefix);
    }
    return ($this->storageClosure)()->listAll();
  }

  /**
   * Finds configuration object names starting with a given prefix.
   *
   * Given the following configuration objects:
   * - node.type.article
   * - node.type.page
   *
   * Passing the prefix 'node.type.' will return an array containing the above
   * names.
   *
   * @param string $prefix
   *   The prefix to search for.
   *
   * @return array
   *   An array containing matching configuration object names.
   */
  protected function findByPrefix($prefix) {
    $cache_key = $this->getCacheKey($prefix);
    if (!isset($this->findByPrefixCache[$cache_key])) {
      $this->findByPrefixCache[$cache_key] = ($this->storageClosure)()->listAll($prefix);
    }
    return $this->findByPrefixCache[$cache_key];
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll($prefix = '') {
    // If the cache was the first to be deleted, another process might start
    // rebuilding the cache before the storage is renamed.
    $names = ($this->storageClosure)()->listAll($prefix);
    if (($this->storageClosure)()->deleteAll($prefix)) {
      $this->cache->deleteMultiple($this->getCacheKeys($names));
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Clears the static list cache.
   */
  public function resetListCache() {
    $this->findByPrefixCache = [];
  }

  /**
   * {@inheritdoc}
   */
  public function createCollection($collection) {
    return new static(
      ($this->storageClosure)()->createCollection($collection),
      $this->cache
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getAllCollectionNames() {
    return ($this->storageClosure)()->getAllCollectionNames();
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionName() {
    return ($this->storageClosure)()->getCollectionName();
  }

  /**
   * Returns a cache key for a configuration name using the collection.
   *
   * @param string $name
   *   The configuration name.
   *
   * @return string
   *   The cache key for the configuration name.
   */
  protected function getCacheKey($name) {
    return $this->getCollectionPrefix() . $name;
  }

  /**
   * Returns a cache key map for an array of configuration names.
   *
   * @param array $names
   *   The configuration names.
   *
   * @return array
   *   An array of cache keys keyed by configuration names.
   */
  protected function getCacheKeys(array $names) {
    $prefix = $this->getCollectionPrefix();
    $cache_keys = array_map(function ($name) use ($prefix) {
      return $prefix . $name;
    }, $names);

    return array_combine($names, $cache_keys);
  }

  /**
   * Returns a cache ID prefix to use for the collection.
   *
   * @return string
   *   The cache ID prefix.
   */
  protected function getCollectionPrefix() {
    if ($this->collection === NULL) {
      $collection = ($this->storageClosure)()->getCollectionName();
    }
    else {
      $collection = $this->collection;
    }
    if ($collection == StorageInterface::DEFAULT_COLLECTION) {
      return '';
    }
    return $collection . ':';
  }

}
