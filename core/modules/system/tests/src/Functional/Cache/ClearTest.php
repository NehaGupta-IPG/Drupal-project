<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Cache;

use Drupal\Core\Cache\Cache;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests our clearing is done the proper way.
 *
 * @group Cache
 */
class ClearTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The default bin for the cache item.
   *
   * @var string
   */
  protected $defaultBin = 'render';

  /**
   * The default cache ID.
   *
   * @var string
   */
  protected $defaultCid = 'test_temporary';

  /**
   * The cache contents default value.
   *
   * @var string
   */
  protected $defaultValue;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->defaultValue = $this->randomMachineName(10);
    parent::setUp();
  }

  /**
   * Tests drupal_flush_all_caches().
   */
  public function testFlushAllCaches(): void {
    // Create cache entries for each flushed cache bin.
    $bins = Cache::getBins();
    $this->assertNotEmpty($bins, 'Cache::getBins() returned bins to flush.');
    foreach ($bins as $bin => $cache_backend) {
      $cid = 'test_cid_clear' . $bin;
      $cache_backend->set($cid, $this->defaultValue);
    }

    // Remove all caches then make sure that they are cleared.
    drupal_flush_all_caches();

    foreach ($bins as $bin => $cache_backend) {
      $cid = 'test_cid_clear' . $bin;
      $this->assertFalse($this->checkCacheExists($cid, $this->defaultValue, $bin), "All cache entries removed from $bin.");
    }
  }

  /**
   * Checks whether or not a cache entry exists.
   *
   * @param string $cid
   *   The cache id.
   * @param mixed $var
   *   The variable the cache should contain.
   * @param string|null $bin
   *   The bin the cache item was stored in.
   *
   * @return bool
   *   TRUE on pass, FALSE on fail.
   */
  protected function checkCacheExists($cid, $var, $bin = NULL): bool {
    if ($bin == NULL) {
      $bin = $this->defaultBin;
    }

    $cached = \Drupal::cache($bin)->get($cid);

    return isset($cached->data) && $cached->data == $var;
  }

  /**
   * Asserts that a cache entry exists.
   *
   * @param string $message
   *   Message to display.
   * @param string|null $var
   *   The variable the cache should contain.
   * @param string|null $cid
   *   The cache id.
   * @param string|null $bin
   *   The bin the cache item was stored in.
   */
  protected function assertCacheExists($message, $var = NULL, $cid = NULL, $bin = NULL) {
    if ($bin == NULL) {
      $bin = $this->defaultBin;
    }
    if ($cid == NULL) {
      $cid = $this->defaultCid;
    }
    if ($var == NULL) {
      $var = $this->defaultValue;
    }

    $this->assertTrue($this->checkCacheExists($cid, $var, $bin), $message);
  }

  /**
   * Asserts that a cache entry has been removed.
   *
   * @param string $message
   *   Message to display.
   * @param string|null $cid
   *   The cache id.
   * @param string|null $bin
   *   The bin the cache item was stored in.
   */
  public function assertCacheRemoved($message, $cid = NULL, $bin = NULL) {
    if ($bin == NULL) {
      $bin = $this->defaultBin;
    }
    if ($cid == NULL) {
      $cid = $this->defaultCid;
    }

    $cached = \Drupal::cache($bin)->get($cid);
    $this->assertFalse($cached, $message);
  }

}
