<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Cache;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\ChainedFastBackend;
use Drupal\Core\Cache\CoordinatedChainedFastBackend;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Cache\ChainedFastBackend.
 */
#[CoversClass(CoordinatedChainedFastBackend::class)]
#[Group('Cache')]
class CoordinatedChainedFastBackendTest extends UnitTestCase {

  /**
   * The cache bin.
   *
   * @var string
   */
  protected $bin;

  /**
   * Tests a get() on the fast backend, with no hit on the consistent backend.
   */
  public function testGetDoesNotHitConsistentBackend(): void {

    // There should be a single call to the invalidation consistent backend for
    // the last_write_timestamp.
    $consistent_invalidation_cache = $this->createMock(CacheBackendInterface::class);
    $timestamp_cid = ChainedFastBackend::LAST_WRITE_TIMESTAMP_PREFIX . 'cache_chained_fast_invalidation';
    // Use the request time because that is what we will be comparing against.
    $timestamp_item = (object) ['cid' => $timestamp_cid, 'data' => (int) $_SERVER['REQUEST_TIME'] - 60];
    $consistent_invalidation_cache->expects($this->once())
      ->method('get')->with($timestamp_cid)
      ->willReturn($timestamp_item);
    $consistent_invalidation_cache->expects($this->never())
      ->method('set');

    // The invalidation fast backend should provide the last write timestamp for
    // the chained fast bin.
    $fast_invalidation_cache = new MemoryBackend(new Time());
    $fast_invalidation_cache->set(ChainedFastBackend::LAST_WRITE_TIMESTAMP_PREFIX . 'cache_foo', $_SERVER['REQUEST_TIME'] - 600);

    $chained_fast_backend = new ChainedFastBackend(
      $consistent_invalidation_cache,
      $fast_invalidation_cache,
      'chained_fast_invalidation'
    );

    // The consistent backend for the chained fast bin should neither have a
    // call for an invalidation timestamp nor for the cache item itself.
    $consistent_cache = $this->createMock(CacheBackendInterface::class);
    $consistent_cache->expects($this->never())
      ->method('get');
    $consistent_cache->expects($this->never())
      ->method('getMultiple');
    $consistent_cache->expects($this->never())
      ->method('set');

    $fast_cache = new MemoryBackend(new Time());
    $fast_cache->set('foo', 'bar');

    $coordinated_backend = new CoordinatedChainedFastBackend(
      $consistent_cache,
      $fast_cache,
      'foo',
      $chained_fast_backend,
    );

    $this->assertEquals('bar', $coordinated_backend->get('foo')->data);
  }

}
