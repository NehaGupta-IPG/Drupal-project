<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Unit\Path;

use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcher;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\system\Path\PathVisibilityMatcher;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the page patterns matcher with core's path matcher.
 */
#[CoversClass(PathVisibilityMatcher::class)]
#[Group('system')]
class PathVisibilityMatcherTest extends UnitTestCase {

  /**
   * Builds a matcher for a current path.
   *
   * @param string $path
   *   The current path.
   * @param \Drupal\path_alias\AliasManagerInterface|null $alias_manager
   *   The alias manager, if any.
   */
  protected function matcher(string $path, ?AliasManagerInterface $alias_manager): PathVisibilityMatcher {
    $request_stack = new RequestStack();
    $request_stack->push(Request::create($path));
    $path_matcher = new PathMatcher(
      $this->getConfigFactoryStub(['system.site' => ['page.front' => '/node/1']]),
      $this->createStub(RouteMatchInterface::class),
    );
    return new PathVisibilityMatcher(
      $path_matcher,
      $request_stack,
      new CurrentPathStack($request_stack),
      $alias_manager,
    );
  }

  /**
   * Data provider for testMatches().
   *
   * @return array<string, array{string, string, string, bool}>
   *   Current path, its alias, lowercased patterns, expected result.
   */
  public static function providerMatches(): array {
    return [
      'wildcard' => ['/blog/x', '/blog/x', '/blog*', TRUE],
      'trailing slash trimmed' => ['/blog/', '/blog/', '/blog', TRUE],
      'root kept' => ['/', '/', '/', TRUE],
      'alias, lowercased' => ['/node/5', '/About', '/about', TRUE],
      'internal path behind alias' => ['/node/5', '/about', '/node/5', TRUE],
      'neither' => ['/node/5', '/about', '/other', FALSE],
      'mixed case internal path' => ['/Node/5', '/Node/5', '/node/5', TRUE],
      'front keyword' => ['/node/1', '/node/1', '<front>', TRUE],
      'front keyword, elsewhere' => ['/node/2', '/node/2', '<front>', FALSE],
      'several lines' => ['/b/x', '/b/x', "/a*\n/b*", TRUE],
    ];
  }

  /**
   * Tests matching against the internal path and its alias.
   */
  #[DataProvider('providerMatches')]
  public function testMatches(string $path, string $alias, string $pages, bool $expected): void {
    $alias_manager = $this->createStub(AliasManagerInterface::class);
    $alias_manager->method('getAliasByPath')->willReturn($alias);
    $this->assertSame($expected, $this->matcher($path, $alias_manager)->matches($pages));
  }

  /**
   * Tests that the alias is not looked up when the internal path matches.
   */
  public function testNoAliasLookupOnInternalMatch(): void {
    $alias_manager = $this->createMock(AliasManagerInterface::class);
    $alias_manager->expects($this->never())->method('getAliasByPath');
    $this->assertTrue($this->matcher('/node/1', $alias_manager)->matches('<front>'));
  }

  /**
   * Tests that only the internal path matches without the path_alias module.
   */
  public function testWithoutAliasManager(): void {
    $this->assertTrue($this->matcher('/node/5', NULL)->matches('/node/*'));
    $this->assertFalse($this->matcher('/node/5', NULL)->matches('/about'));
  }

}
