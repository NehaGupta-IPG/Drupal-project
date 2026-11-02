<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\PathConditionCacheContext;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests Drupal\Core\Cache\Context\PathConditionCacheContext.
 */
#[CoversClass(PathConditionCacheContext::class)]
#[Group('Cache')]
class PathConditionCacheContextTest extends UnitTestCase {

  /**
   * Tests get context.
   */
  #[DataProvider('providerTestGetContext')]
  public function testGetContext(string $path, ?string $patterns, bool $matches, string $expected): void {
    $request_stack = new RequestStack();
    $request_stack->push(Request::create($path));

    $path_matcher = $this->createMock(PathMatcherInterface::class);
    if ($patterns === NULL || $patterns === '') {
      $path_matcher->expects($this->never())->method('matchPath');
    }
    else {
      $path_matcher->expects($this->once())
        ->method('matchPath')
        ->with($path, $patterns)
        ->willReturn($matches);
    }

    $cache_context = new PathConditionCacheContext($path_matcher, $request_stack);
    $this->assertSame($expected, $cache_context->getContext($patterns));
  }

  /**
   * Provides a list of paths, patterns and expected cache context values.
   */
  public static function providerTestGetContext(): array {
    return [
      ['/node/1', NULL, FALSE, 'no-patterns'],
      ['/node/1', '', FALSE, 'no-patterns'],
      ['/node/1', '/node/*', TRUE, 'match'],
      ['/node/1', '/user/*', FALSE, 'no-match'],
    ];
  }

  /**
   * Tests get cacheable metadata.
   */
  #[AllowMockObjectsWithoutExpectations]
  public function testGetCacheableMetadata(): void {
    $request_stack = new RequestStack();
    $request_stack->push(Request::create('/node/1'));
    $path_matcher = $this->createMock(PathMatcherInterface::class);

    $cache_context = new PathConditionCacheContext($path_matcher, $request_stack);
    $this->assertEquals(new CacheableMetadata(), $cache_context->getCacheableMetadata('/node/*'));
  }

}
