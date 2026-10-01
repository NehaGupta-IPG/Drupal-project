<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Unit\Cache\Context;

use Drupal\system\Cache\Context\PathMatchesCacheContext;
use Drupal\system\Path\PathPatternsMap;
use Drupal\system\Path\PathVisibilityMatcher;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the url.path.matches cache context in isolation.
 */
#[CoversClass(PathMatchesCacheContext::class)]
#[Group('Cache')]
class PathMatchesCacheContextTest extends UnitTestCase {

  /**
   * Builds a context on a map holding one hash, and a matcher's answer.
   *
   * @param string $pages
   *   The patterns the map returns for the hash 'known'.
   * @param bool $matches
   *   What the matcher answers for these patterns.
   */
  protected function context(string $pages, bool $matches = FALSE): PathMatchesCacheContext {
    $map = $this->createStub(PathPatternsMap::class);
    $map->method('get')->willReturnCallback(
      fn(string $hash) => $hash === 'known' ? $pages : NULL,
    );
    $matcher = $this->createStub(PathVisibilityMatcher::class);
    $matcher->method('matches')->willReturnCallback(
      function (string $matched) use ($pages, $matches) {
        $this->assertSame($pages, $matched);
        return $matches;
      },
    );
    $request_stack = new RequestStack();
    $request_stack->push(Request::create('/missing/page'));
    return new PathMatchesCacheContext($map, $matcher, $request_stack);
  }

  /**
   * Data provider for testGetContext().
   *
   * @return array<string, array{string|null, bool, string}>
   *   Parameter, condition result, expected value.
   */
  public static function providerGetContext(): array {
    return [
      'match' => ['known', TRUE, '1'],
      'no match' => ['known', FALSE, '0'],
      'unknown hash falls back to the path' => ['unknown', TRUE, '/missing/page'],
      'no parameter falls back to the path' => [NULL, TRUE, '/missing/page'],
    ];
  }

  /**
   * Tests the context value.
   */
  #[DataProvider('providerGetContext')]
  public function testGetContext(?string $parameter, bool $matches, string $expected): void {
    $this->assertSame($expected, $this->context('/blog*', $matches)->getContext($parameter));
  }

  /**
   * Data provider for testGetCacheableMetadata().
   *
   * @return array<string, array{string|null, string, list<string>}>
   *   Parameter, patterns for the known hash, expected cache tags.
   */
  public static function providerCacheableMetadata(): array {
    return [
      'front keyword' => ['known', "/a\n<front>", ['config:system.site']],
      'no front keyword' => ['known', '/a', []],
      'unknown hash' => ['unknown', '<front>', []],
      'no parameter' => [NULL, '<front>', []],
    ];
  }

  /**
   * Tests that only the front keyword depends on the site settings.
   */
  #[DataProvider('providerCacheableMetadata')]
  public function testGetCacheableMetadata(?string $parameter, string $pages, array $tags): void {
    $metadata = $this->context($pages)->getCacheableMetadata($parameter);
    $this->assertSame($tags, $metadata->getCacheTags());
    $this->assertSame([], $metadata->getCacheContexts());
  }

}
