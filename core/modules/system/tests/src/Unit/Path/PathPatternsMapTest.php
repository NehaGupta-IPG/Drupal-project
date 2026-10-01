<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Unit\Path;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\system\Path\PathPatternsMap;
use Drupal\system\Path\PathPatternsProviderInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests how the page patterns map is built, cached and invalidated.
 */
#[CoversClass(PathPatternsMap::class)]
#[Group('system')]
class PathPatternsMapTest extends UnitTestCase {

  /**
   * Builds a provider stub.
   *
   * @param string[] $patterns
   *   The page patterns it provides.
   * @param string[] $tags
   *   Its cache tags.
   */
  protected function provider(array $patterns, array $tags): PathPatternsProviderInterface {
    $provider = $this->createStub(PathPatternsProviderInterface::class);
    $provider->method('getPatterns')->willReturn($patterns);
    $provider->method('getCacheTags')->willReturn($tags);
    return $provider;
  }

  /**
   * Tests that hashes are stable, distinct, case-insensitive and colon-free.
   */
  public function testHash(): void {
    $hash = PathPatternsMap::hash('/a*');
    $this->assertSame($hash, PathPatternsMap::hash('/A*'));
    $this->assertNotSame($hash, PathPatternsMap::hash('/b*'));
    $this->assertSame(43, strlen($hash));
    $this->assertStringNotContainsString(':', $hash);
  }

  /**
   * Tests building on a cache miss, from all providers, without cache tags.
   */
  public function testBuildOnMiss(): void {
    $expected = [
      PathPatternsMap::hash('/blog*') => '/blog*',
      PathPatternsMap::hash('<front>') => '<front>',
      PathPatternsMap::hash("/a\n/b") => "/a\n/b",
    ];
    $cid = PathPatternsMap::CID . ':tag_a,tag_b';
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->once())->method('get')->with($cid)->willReturn(FALSE);
    $cache->expects($this->once())->method('set')->with($cid, $expected);

    $map = new PathPatternsMap($cache);
    $map->addProvider($this->provider(['/Blog*', '<front>', '', '0'], ['tag_b']));
    $map->addProvider($this->provider(["/a\n/b", '/blog*'], ['tag_a']));
    $this->assertSame('/blog*', $map->get(PathPatternsMap::hash('/Blog*')));
    $this->assertNull($map->get(PathPatternsMap::hash('')));
  }

  /**
   * Tests that without providers the map is empty and the cache untouched.
   */
  public function testNoProvider(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->never())->method('get');
    $cache->expects($this->never())->method('set');
    $cache->expects($this->never())->method('delete');

    $map = new PathPatternsMap($cache);
    $this->assertNull($map->get(PathPatternsMap::hash('/a')));
    $map->invalidateTags(['tag']);
  }

  /**
   * Tests the cache hit, the in-request copy, and the explicit invalidation.
   */
  public function testCacheHitAndInvalidation(): void {
    $hash = PathPatternsMap::hash('/a');
    $cid = PathPatternsMap::CID . ':tag';
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->exactly(2))->method('get')->with($cid)->willReturnOnConsecutiveCalls(
      (object) ['data' => [$hash => '/a']],
      (object) ['data' => []],
    );
    $cache->expects($this->never())->method('set');
    $cache->expects($this->once())->method('delete')->with($cid);

    $map = new PathPatternsMap($cache);
    $map->addProvider($this->provider([], ['tag']));
    $this->assertSame('/a', $map->get($hash));
    // Served from the in-request copy, without a second cache read.
    $this->assertSame('/a', $map->get($hash));
    // An unrelated tag leaves the map alone.
    $map->invalidateTags(['other']);
    $this->assertSame('/a', $map->get($hash));
    // The provider's tag deletes the cached map and the in-request copy.
    $map->invalidateTags(['other', 'tag']);
    $this->assertNull($map->get($hash));
  }

}
