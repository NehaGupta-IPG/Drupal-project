<?php

declare(strict_types=1);

namespace Drupal\Tests\block\Kernel;

use Drupal\block\Entity\Block;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\system\Path\PathPatternsMap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests how blocks limited by request path vary in cache.
 */
#[Group('block')]
#[RunTestsInSeparateProcesses]
class BlockRequestPathCacheTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'path_alias',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('user');
    $this->container->get('theme_installer')->install(['stark']);
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Makes a request current, as routing or a 404 subrequest would.
   *
   * @param string $path
   *   The requested path.
   * @param string $route_name
   *   The route it resolved to: a 404 subrequest keeps the original path and
   *   only routes to system.404.
   * @param string|null $internal_path
   *   The internal path inbound processing resolved an alias to, if any.
   */
  protected function setCurrentRequest(string $path, string $route_name, ?string $internal_path = NULL): void {
    $route = $this->container->get('router.route_provider')
      ->getRouteByName($route_name);
    $request = Request::create($path);
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, $route_name);
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set('_raw_variables', new InputBag());
    $stack = $this->container->get('request_stack');
    $request->setSession($stack->getCurrentRequest()->getSession());
    $stack->push($request);
    if ($internal_path !== NULL) {
      $this->container->get('path.current')->setPath($internal_path, $request);
    }
  }

  /**
   * Places a block limited by request path.
   *
   * @param string $id
   *   The block ID.
   * @param string $pages
   *   The page patterns.
   * @param bool $negate
   *   Whether to negate the condition.
   */
  protected function placeBlock(string $id, string $pages, bool $negate = FALSE): Block {
    $block = Block::create([
      'id' => $id,
      'theme' => 'stark',
      'region' => 'content',
      'plugin' => 'system_powered_by_block',
      'visibility' => [
        'request_path' => [
          'id' => 'request_path',
          'pages' => $pages,
          'negate' => $negate,
        ],
      ],
    ]);
    $block->save();
    return $block;
  }

  /**
   * Checks block view access for the current request, bypassing its cache.
   */
  protected function access(Block $block): AccessResultInterface {
    $this->container->get('entity_type.manager')
      ->getAccessControlHandler('block')
      ->resetCache();
    return $block->access('view', NULL, TRUE);
  }

  /**
   * Computes the cache keys an access result varies by.
   *
   * @return string[]
   *   The cache keys.
   */
  protected function keys(AccessResultInterface $access): array {
    $contexts = CacheableMetadata::createFromObject($access)->getCacheContexts();
    return $this->container->get('cache_contexts_manager')
      ->convertTokensToKeys($contexts)
      ->getKeys();
  }

  /**
   * Data provider for testSameKeysSameDecision().
   *
   * @return array<string, array{string, array<string, array{string, string, string|null, bool}>}>
   *   Page patterns, then per request: requested path, route name, internal
   *   path behind an alias if any, and whether the block is shown.
   */
  public static function providerSameKeysSameDecision(): array {
    // The alias /signin points to /user/login.
    $aliased = [
      'by alias' => ['/signin', 'user.login', '/user/login', TRUE],
      'by internal path' => ['/user/login', 'user.login', NULL, TRUE],
      'other page' => ['/other', 'system.404', NULL, FALSE],
    ];
    return [
      'two lines, 404 pages' => [
        "/blog*\n/news",
        [
          '/missing-a' => ['/missing-a', 'system.404', NULL, FALSE],
          '/missing-b' => ['/missing-b', 'system.404', NULL, FALSE],
          '/blog/missing' => ['/blog/missing', 'system.404', NULL, TRUE],
          '/news' => ['/news', 'system.404', NULL, TRUE],
        ],
      ],
      'mixed case pattern' => [
        '/Blog*',
        [
          '/blog/x' => ['/blog/x', 'system.404', NULL, TRUE],
          '/other' => ['/other', 'system.404', NULL, FALSE],
        ],
      ],
      'pattern names the alias' => ['/signin', $aliased],
      'pattern names the internal path' => ['/user/login', $aliased],
      'single line, 404 pages' => [
        '/blog*',
        [
          '/missing-a' => ['/missing-a', 'system.404', NULL, FALSE],
          '/missing-b' => ['/missing-b', 'system.404', NULL, FALSE],
          '/blog/missing' => ['/blog/missing', 'system.404', NULL, TRUE],
        ],
      ],
    ];
  }

  /**
   * Tests that requests sharing cache keys share the visibility decision.
   *
   * Requests with the same cache keys are served the same cached output, so
   * they must agree on whether the block shows. The block only has two
   * outcomes, so two sets of keys are also all it should need.
   *
   * @param string $pages
   *   The page patterns of the block.
   * @param array<string, array{string, string, string|null, bool}> $requests
   *   The requests, keyed by label.
   */
  #[DataProvider('providerSameKeysSameDecision')]
  public function testSameKeysSameDecision(string $pages, array $requests): void {
    PathAlias::create(['path' => '/user/login', 'alias' => '/signin'])->save();
    $block = $this->placeBlock('limited', $pages);

    $decisions = [];
    foreach ($requests as $label => [$path, $route_name, $internal_path, $shown]) {
      $this->setCurrentRequest($path, $route_name, $internal_path);
      $access = $this->access($block);
      $this->assertSame($shown, $access->isAllowed(), $label);
      $keys = $this->keys($access);
      sort($keys);
      $decisions[implode(' ', $keys)][$label] = $shown;
    }

    foreach ($decisions as $labels) {
      $this->assertCount(1, array_unique($labels), 'Requests sharing cache keys disagree: ' . implode(', ', array_keys($labels)));
    }
    $this->assertCount(2, $decisions, 'One set of cache keys per outcome.');
  }

  /**
   * Tests that 404 pages share variations while decisions stay per path.
   */
  public function test404Pages(): void {
    $block = $this->placeBlock('blog', "/blog*\n/news");
    $contexts_manager = $this->container->get('cache_contexts_manager');

    $this->setCurrentRequest('/missing-a', 'system.404');
    $a = $this->access($block);
    $this->assertFalse($a->isAllowed());
    $this->assertNotContains('url.path', CacheableMetadata::createFromObject($a)->getCacheContexts());
    $keys_a = $this->keys($a);
    // Control: url.path would have varied here.
    $path_a = $contexts_manager->convertTokensToKeys(['url.path'])->getKeys();

    $this->setCurrentRequest('/missing-b', 'system.404');
    $b = $this->access($block);
    $this->assertFalse($b->isAllowed());
    $this->assertSame($keys_a, $this->keys($b));
    $this->assertNotSame($path_a, $contexts_manager->convertTokensToKeys(['url.path'])->getKeys());

    // A 404 under a listed path still shows the block, and so gets the other
    // variation.
    $this->setCurrentRequest('/blog/missing', 'system.404');
    $c = $this->access($block);
    $this->assertTrue($c->isAllowed());
    $keys_c = $this->keys($c);
    $this->assertNotSame($keys_a, $keys_c);

    // Any line of the patterns gives that same variation.
    $this->setCurrentRequest('/news', 'system.404');
    $d = $this->access($block);
    $this->assertTrue($d->isAllowed());
    $this->assertSame($keys_c, $this->keys($d));
  }

  /**
   * Tests the front keyword, shown and hidden, and its settings dependency.
   */
  public function testFront(): void {
    $this->config('system.site')->set('page.front', '/user/login')->save();
    $shown = $this->placeBlock('shown', '<front>');
    $hidden = $this->placeBlock('hidden', '<front>', TRUE);

    $this->setCurrentRequest('/user/login', 'user.login');
    $this->assertTrue($this->access($shown)->isAllowed());
    $this->assertFalse($this->access($hidden)->isAllowed());

    // When url.path is also present, it absorbs url.path.matches, which then
    // only contributes its cacheability: the front page setting.
    $token = 'url.path.matches:' . PathPatternsMap::hash('<front>');
    $keys = $this->container->get('cache_contexts_manager')
      ->convertTokensToKeys(['url.path', $token]);
    $this->assertCount(1, $keys->getKeys());
    $this->assertStringStartsWith('[url.path]=', $keys->getKeys()[0]);
    $this->assertContains('config:system.site', $keys->getCacheTags());

    $this->setCurrentRequest('/user/register', 'user.register');
    $this->assertFalse($this->access($shown)->isAllowed());
    $this->assertTrue($this->access($hidden)->isAllowed());
  }

  /**
   * Tests that patterns differing by case share a hash and still match.
   */
  public function testCase(): void {
    $block = $this->placeBlock('mixed_case', '/Blog*');
    $this->setCurrentRequest('/blog/x', 'system.404');
    $access = $this->access($block);
    $this->assertTrue($access->isAllowed());
    $token = 'url.path.matches:' . PathPatternsMap::hash('/blog*');
    $this->assertContains($token, CacheableMetadata::createFromObject($access)->getCacheContexts());
    $this->assertSame('1', $this->container->get('cache_context.url.path.matches')->getContext(PathPatternsMap::hash('/blog*')));
  }

  /**
   * Tests that the map follows block changes and falls back when unknown.
   */
  public function testMapFollowsBlocks(): void {
    $this->placeBlock('a', '/a*');
    $b = $this->placeBlock('b', '/b*');
    $map = $this->container->get('system.path_patterns_map');
    $context = $this->container->get('cache_context.url.path.matches');
    $hash_a = PathPatternsMap::hash('/a*');
    $hash_b = PathPatternsMap::hash('/b*');
    $this->assertSame('/a*', $map->get($hash_a));
    $this->assertSame('/b*', $map->get($hash_b));

    $b->delete();
    $this->assertSame('/a*', $map->get($hash_a));
    $this->assertNull($map->get($hash_b));

    $this->setCurrentRequest('/b/x', 'system.404');
    $this->assertSame('/b/x', $context->getContext($hash_b));
    $this->setCurrentRequest('/a/x', 'system.404');
    $this->assertSame('1', $context->getContext($hash_a));
  }

  /**
   * Tests that without patterns the condition neither filters nor varies.
   */
  public function testEmptyPatterns(): void {
    $condition = $this->container->get('plugin.manager.condition')
      ->createInstance('request_path', ['pages' => '']);
    $this->assertTrue($condition->evaluate());
    foreach ($condition->getCacheContexts() as $context) {
      $this->assertStringStartsNotWith('url.path', $context);
    }
  }

}
