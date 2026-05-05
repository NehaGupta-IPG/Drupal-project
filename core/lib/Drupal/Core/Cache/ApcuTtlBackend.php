<?php

namespace Drupal\Core\Cache;

use Drupal\Component\Assertion\Inspector;

/**
 * Stores cache items in the Alternative PHP Cache User Cache (APCu).
 *
 * This backend attempts to avoid the situation where the APCu cache becomes
 * full and restarts. APCu does not implement an LRU or similar strategy, but
 * instead when it attempts to insert an item, if the cache is full, it restarts
 * to begin again with an empty cache. To avoid this, immediately after
 * a restart, we set cache items with short TTL, then increase the TTL the
 * longer the cache has been running. Doing so should allow less frequently
 * accessed items to be expired via the TTL when new items are set, reclaiming
 * space without a restart.
 *
 * There are at least two scenarios where the cache could still become full.
 * Where the cache is filled less than minimum TTL of 60 seconds, there will
 * be no opportunity for APCu to evict items older than the TTL. Or where the
 * number and size of frequently accessed cache entries exceeds the available
 * space.
 *
 */
class ApcuTtlBackend extends ApcuBackend {

  /**
   * A start time for the first recorded write to APCu.
   */
  protected ?int $apcuFirstWrite = NULL;


  /**
   * Gets the APCu first write timestamp.
   *
   * @return int
   *   The APCu first write timestamp as a UNIX timestamp.
   */
  protected function getApcuFirstWrite(): int {
    if (!isset($this->apcuFirstWrite)) {
      $cid = $this->getApcuKey('apcu_start_time');
      $start_time = apcu_fetch($cid);
      if ($start_time) {
        $this->apcuFirstWrite = $start_time;
      }
      else {
        $this->apcuFirstWrite = time();
        apcu_store($cid, $this->apcuFirstWrite);
      }
    }
    return $this->apcuFirstWrite;
  }

  /**
   * {@inheritdoc}
   */
  public function set($cid, $data, $expire = CacheBackendInterface::CACHE_PERMANENT, array $tags = []): void {
    // Set a TTL to either the time since the first write timestamp or 10
    // seconds, whichever is greater.This will result in progressively longer
    // TTLs as the time since first write progresses.
    $created = round(microtime(TRUE), 3);
    $created_timestamp = (int) $created;
    $first_write = $this->getApcuFirstWrite();

    $ttl = max(10, $created_timestamp - $first_write);

    assert(Inspector::assertAllStrings($tags), 'Cache tags must be strings.');
    $tags = array_unique($tags);
    $cache = new \stdClass();
    $cache->cid = $cid;
    $cache->created = $created;
    $cache->expire = $expire;
    $cache->tags = implode(' ', $tags);
    $cache->checksum = $this->checksumProvider->getCurrentChecksum($tags);
    // APCu serializes/unserializes any structure itself.
    $cache->serialized = 0;
    $cache->data = $data;

    // Expiration is handled by our own prepareItem(), not APCu.
    apcu_store($this->getApcuKey($cid), $cache, $ttl);
  }

}
