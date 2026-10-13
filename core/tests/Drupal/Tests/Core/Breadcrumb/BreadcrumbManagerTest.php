<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Breadcrumb;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbManager;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Cache\MemoryCache\MemoryCache;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;

/**
 * Tests Drupal\Core\Breadcrumb\BreadcrumbManager.
 */
#[CoversClass(BreadcrumbManager::class)]
#[Group('Breadcrumb')]
class BreadcrumbManagerTest extends UnitTestCase {

  /**
   * The dependency injection container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * The breadcrumb object.
   *
   * @var \Drupal\Core\Breadcrumb\Breadcrumb
   */
  protected $breadcrumb;

  /**
   * The tested breadcrumb manager.
   *
   * @var \Drupal\Core\Breadcrumb\BreadcrumbManager
   */
  protected $breadcrumbManager;

  /**
   * The mocked module handler.
   */
  protected ModuleHandlerInterface&MockObject $moduleHandler;

  /**
   * The memory cache.
   */
  protected MemoryCache $memoryCache;

  /**
   * The request stack.
   */
  protected RequestStack $requestStack;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->moduleHandler = $this->createMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $this->requestStack = new RequestStack();
    $this->requestStack->push(new Request());
    $time = $this->createStub(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(time());
    $this->memoryCache = new MemoryCache($time);
    $this->breadcrumbManager = new BreadcrumbManager($this->moduleHandler, $this->memoryCache, $this->requestStack);
    $this->breadcrumb = new Breadcrumb();

    $this->container = new ContainerBuilder();
    $cache_contexts_manager = $this->prophesize(CacheContextsManager::class);
    $cache_contexts_manager->assertValidTokens()->willReturn(TRUE);
    $cache_contexts_manager->reveal();
    $this->container->set('cache_contexts_manager', $cache_contexts_manager);
    \Drupal::setContainer($this->container);
  }

  /**
   * Tests the breadcrumb manager without any set breadcrumb.
   */
  public function testBuildWithoutBuilder(): void {
    $route_match = $this->createStub(RouteMatchInterface::class);
    $this->moduleHandler->expects($this->once())
      ->method('alter')
      ->with('system_breadcrumb', $this->breadcrumb, $route_match, ['builder' => NULL]);

    $breadcrumb = $this->breadcrumbManager->build($this->createStub(RouteMatchInterface::class));
    $this->assertEquals([], $breadcrumb->getLinks());
    $this->assertEquals([], $breadcrumb->getCacheContexts());
    $this->assertEquals([], $breadcrumb->getCacheTags());
    $this->assertEquals(Cache::PERMANENT, $breadcrumb->getCacheMaxAge());
  }

  /**
   * Tests the build method with a single breadcrumb builder.
   */
  public function testBuildWithSingleBuilder(): void {
    $builder = $this->createMock('Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface');
    $links = ['<a href="/example">Test</a>'];
    $this->breadcrumb->setLinks($links);
    $this->breadcrumb->addCacheContexts(['foo'])->addCacheTags(['bar']);

    $builder->expects($this->once())
      ->method('applies')
      ->willReturn(TRUE);

    $builder->expects($this->once())
      ->method('build')
      ->willReturn($this->breadcrumb);

    $route_match = $this->createStub(RouteMatchInterface::class);
    $this->moduleHandler->expects($this->once())
      ->method('alter')
      ->with('system_breadcrumb', $this->breadcrumb, $route_match, ['builder' => $builder]);

    $this->breadcrumbManager->addBuilder($builder, 0);

    $breadcrumb = $this->breadcrumbManager->build($route_match);
    $this->assertEquals($links, $breadcrumb->getLinks());
    $this->assertEquals(['foo'], $breadcrumb->getCacheContexts());
    $this->assertEquals(['bar'], $breadcrumb->getCacheTags());
    $this->assertEquals(Cache::PERMANENT, $breadcrumb->getCacheMaxAge());
  }

  /**
   * Tests multiple breadcrumb builder with different priority.
   */
  public function testBuildWithMultipleApplyingBuilders(): void {
    $builder1 = $this->createMock('Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface');
    $builder1->expects($this->never())
      ->method('applies');
    $builder1->expects($this->never())
      ->method('build');

    $builder2 = $this->createMock('Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface');
    $links2 = ['<a href="/example2">Test2</a>'];
    $this->breadcrumb->setLinks($links2);
    $this->breadcrumb->addCacheContexts(['baz'])->addCacheTags(['qux']);
    $builder2->expects($this->once())
      ->method('applies')
      ->willReturn(TRUE);
    $builder2->expects($this->once())
      ->method('build')
      ->willReturn($this->breadcrumb);

    $route_match = $this->createStub(RouteMatchInterface::class);

    $this->moduleHandler->expects($this->once())
      ->method('alter')
      ->with('system_breadcrumb', $this->breadcrumb, $route_match, ['builder' => $builder2]);

    $this->breadcrumbManager->addBuilder($builder1, 0);
    $this->breadcrumbManager->addBuilder($builder2, 10);

    $breadcrumb = $this->breadcrumbManager->build($route_match);
    $this->assertEquals($links2, $breadcrumb->getLinks());
    $this->assertEquals(['baz'], $breadcrumb->getCacheContexts());
    $this->assertEquals(['qux'], $breadcrumb->getCacheTags());
    $this->assertEquals(Cache::PERMANENT, $breadcrumb->getCacheMaxAge());
  }

  /**
   * Tests multiple breadcrumb builders of which one returns NULL.
   */
  public function testBuildWithOneNotApplyingBuilders(): void {
    $builder1 = $this->createMock('Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface');
    $builder1->expects($this->once())
      ->method('applies')
      ->willReturn(FALSE);
    $builder1->expects($this->never())
      ->method('build');

    $builder2 = $this->createMock('Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface');
    $links2 = ['<a href="/example2">Test2</a>'];
    $this->breadcrumb->setLinks($links2);
    $this->breadcrumb->addCacheContexts(['baz'])->addCacheTags(['qux']);
    $builder2->expects($this->once())
      ->method('applies')
      ->willReturn(TRUE);
    $builder2->expects($this->once())
      ->method('build')
      ->willReturn($this->breadcrumb);

    $route_match = $this->createStub(RouteMatchInterface::class);

    $this->moduleHandler->expects($this->once())
      ->method('alter')
      ->with('system_breadcrumb', $this->breadcrumb, $route_match, ['builder' => $builder2]);

    $this->breadcrumbManager->addBuilder($builder1, 10);
    $this->breadcrumbManager->addBuilder($builder2, 0);

    $breadcrumb = $this->breadcrumbManager->build($route_match);
    $this->assertEquals($links2, $breadcrumb->getLinks());
    $this->assertEquals(['baz'], $breadcrumb->getCacheContexts());
    $this->assertEquals(['qux'], $breadcrumb->getCacheTags());
    $this->assertEquals(Cache::PERMANENT, $breadcrumb->getCacheMaxAge());
  }

  /**
   * Tests a breadcrumb builder with a bad return value.
   */
  public function testBuildWithInvalidBreadcrumbResult(): void {
    $this->moduleHandler->expects($this->never())
      ->method('alter');

    $builder = $this->createMock('Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface');
    $builder->expects($this->once())
      ->method('applies')
      ->willReturn(TRUE);
    $builder->expects($this->once())
      ->method('build')
      ->willReturn('invalid_result');

    $this->breadcrumbManager->addBuilder($builder, 0);
    $this->expectException(\UnexpectedValueException::class);
    $this->breadcrumbManager->build($this->createStub('Drupal\Core\Routing\RouteMatchInterface'));
  }

  /**
   * Tests that breadcrumbs are statically cached per request and route match.
   */
  public function testBuildStaticCache(): void {
    $this->breadcrumb->setLinks(['<a href="/example">Test</a>']);
    $this->breadcrumb->addCacheTags(['node:1']);

    $builder = $this->createMock('Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface');
    $builder->expects($this->exactly(4))
      ->method('applies')
      ->willReturn(TRUE);
    $builder->expects($this->exactly(4))
      ->method('build')
      ->willReturnCallback(fn () => clone $this->breadcrumb);
    $this->moduleHandler->expects($this->exactly(4))
      ->method('alter');
    $this->breadcrumbManager->addBuilder($builder, 0);

    $route = new Route('/node/{node}');
    $route_match_1 = new RouteMatch('entity.node.canonical', $route, [], ['node' => '1']);
    $route_match_1_again = new RouteMatch('entity.node.canonical', $route, [], ['node' => '1']);
    $route_match_2 = new RouteMatch('entity.node.canonical', $route, [], ['node' => '2']);

    $breadcrumb_1 = $this->breadcrumbManager->build($route_match_1);
    // An equivalent route match is served from the static cache.
    $breadcrumb_1_again = $this->breadcrumbManager->build($route_match_1_again);
    $this->assertEquals($breadcrumb_1, $breadcrumb_1_again);
    // Each call returns a separate object so the cache cannot be altered.
    $this->assertNotSame($breadcrumb_1, $breadcrumb_1_again);
    $breadcrumb_1_again->addCacheTags(['foo']);
    $this->assertEquals(['node:1'], $this->breadcrumbManager->build($route_match_1)->getCacheTags());

    // Different route parameters build a new breadcrumb.
    $breadcrumb_2 = $this->breadcrumbManager->build($route_match_2);
    $this->assertEquals($this->breadcrumb->getLinks(), $breadcrumb_2->getLinks());
    $this->assertNotSame($breadcrumb_1, $breadcrumb_2);

    // A new request does not reuse breadcrumbs from the previous request.
    $this->requestStack->push(new Request());
    $breadcrumb_new_request = $this->breadcrumbManager->build($route_match_1);
    $this->assertEquals($this->breadcrumb->getLinks(), $breadcrumb_new_request->getLinks());
    $this->assertEquals(['node:1'], $breadcrumb_new_request->getCacheTags());
    // The breadcrumb is then cached for the new request.
    $this->assertEquals($breadcrumb_new_request, $this->breadcrumbManager->build($route_match_1));

    // Invalidating a cache tag of the breadcrumb builds it again.
    $this->memoryCache->invalidateTags(['node:1']);
    $breadcrumb_invalidated = $this->breadcrumbManager->build($route_match_1);
    $this->assertEquals($this->breadcrumb->getLinks(), $breadcrumb_invalidated->getLinks());
    $this->assertEquals(['node:1'], $breadcrumb_invalidated->getCacheTags());
  }

  /**
   * Tests the deprecation of calling the constructor without all arguments.
   */
  #[IgnoreDeprecations]
  public function testConstructorDeprecation(): void {
    $this->container->set('cache.memory', $this->memoryCache);
    $this->container->set('request_stack', $this->requestStack);
    $this->expectUserDeprecationMessage('Calling Drupal\Core\Breadcrumb\BreadcrumbManager::__construct() without the $memoryCache argument is deprecated in drupal:12.1.0 and will be required in drupal:13.0.0. See https://www.drupal.org/node/CHANGE_RECORD');
    $this->expectUserDeprecationMessage('Calling Drupal\Core\Breadcrumb\BreadcrumbManager::__construct() without the $requestStack argument is deprecated in drupal:12.1.0 and will be required in drupal:13.0.0. See https://www.drupal.org/node/CHANGE_RECORD');
    $this->moduleHandler->expects($this->never())->method('alter');
    new BreadcrumbManager($this->moduleHandler);
  }

}
