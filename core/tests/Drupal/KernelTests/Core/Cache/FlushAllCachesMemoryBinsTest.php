<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Cache;

use Drupal\Core\Cache\Cache;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcesses;

/**
 * Tests that drupal_flush_all_caches() flushes memory cache bins.
 * Regression: https://www.drupal.org/project/drupal/issues/XXXXXXX
 *
 * In long-running PHP processes (Drush daemons, Swoole, RoadRunner, PHP-PM,
 * ReactPHP, MCP servers), memory bins survive across requests. Without this
 * fix, a cache clear leaves stale data in memory bins for the lifetime of
 * the process.
 */
#[Group('Cache')]
#[RunInSeparateProcesses]
class FlushAllCachesMemoryBinsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Tests memory bins are cleared by drupal_flush_all_caches().
   */
  public function testFlushAllCachesIncludesMemoryBins(): void {
    $memory_bins = Cache::getMemoryBins();

    if (empty($memory_bins)) {
      $this->markTestSkipped('No memory cache bins registered in this environment.');
    }

    // Populate each registered memory bin with a known value.
    foreach ($memory_bins as $bin_id => $memory_backend) {
      $memory_backend->set('test_key_' . $bin_id, 'stale_value', Cache::PERMANENT);
    }

    // Verify values exist before the flush.
    foreach ($memory_bins as $bin_id => $memory_backend) {
      $this->assertNotFalse(
        $memory_backend->get('test_key_' . $bin_id),
        sprintf('Memory bin "%s" should contain the test item before flush.', $bin_id)
      );
    }

    // Flush all caches — this is the function under test.
    drupal_flush_all_caches();

    // Re-fetch bins in case the container was rebuilt during flush.
    foreach (Cache::getMemoryBins() as $bin_id => $memory_backend) {
      $this->assertFalse(
        $memory_backend->get('test_key_' . $bin_id),
        sprintf(
          'Memory bin "%s" must be empty after drupal_flush_all_caches(). ' .
          'See https://www.drupal.org/project/drupal/issues/XXXXXXX',
          $bin_id
        )
      );
    }
  }

}