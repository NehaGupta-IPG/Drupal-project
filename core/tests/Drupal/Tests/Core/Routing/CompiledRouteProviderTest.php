<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Routing;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Routing\CompiledRouteProvider;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Routing\RouteStorage\CompiledRouterData;
use Drupal\Core\Routing\RouteStorage\RouteStorageInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Tests \Drupal\Core\Routing\CompiledRouteProvider.
 */
#[CoversClass(CompiledRouteProvider::class)]
#[Group('Routing')]
class CompiledRouteProviderTest extends UnitTestCase {

  /**
   * Builds a CompiledRouteProvider wrapping the given CompiledRouterData.
   */
  protected function buildProvider(?CompiledRouterData $data, ?CacheTagsInvalidatorInterface $invalidator = NULL): CompiledRouteProvider {
    $storage = $this->createStub(RouteStorageInterface::class);
    $storage->method('load')->willReturn($data);

    $language = $this->createStub(LanguageInterface::class);
    $language->method('getId')->willReturn('en');
    $languageManager = $this->createStub(LanguageManagerInterface::class);
    $languageManager->method('getCurrentLanguage')->willReturn($language);

    return new CompiledRouteProvider(
      $storage,
      $this->createStub(RouteBuilderInterface::class),
      $this->createStub(CurrentPathStack::class),
      $this->createStub(CacheBackendInterface::class),
      $this->createStub(InboundPathProcessorInterface::class),
      $invalidator ?? $this->createStub(CacheTagsInvalidatorInterface::class),
      $languageManager,
    );
  }

  /**
   * Builds a CompiledRouterData with a small canonical set of routes.
   */
  protected function buildData(): CompiledRouterData {
    $collection = new RouteCollection();
    $collection->add('a', new Route('/a'));
    $collection->add('b', new Route('/b'));
    $collection->addAlias('alias.a', 'a');
    return CompiledRouterData::fromCollection($collection);
  }

  /**
   * Tests getRouteByName() resolves aliases and surfaces Route objects.
   */
  public function testGetRouteByName(): void {
    $provider = $this->buildProvider($this->buildData());
    $this->assertSame('/a', $provider->getRouteByName('a')->getPath());
    $this->assertSame('/a', $provider->getRouteByName('alias.a')->getPath());
  }

  /**
   * Tests getRouteByName() throws RouteNotFoundException for unknown routes.
   */
  public function testGetRouteByNameUnknown(): void {
    $provider = $this->buildProvider($this->buildData());
    $this->expectException(RouteNotFoundException::class);
    $provider->getRouteByName('missing');
  }

  /**
   * Tests getRouteByName() throws when no data has been loaded.
   */
  public function testGetRouteByNameWithoutData(): void {
    $provider = $this->buildProvider(NULL);
    $this->expectException(RouteNotFoundException::class);
    $provider->getRouteByName('a');
  }

  /**
   * Tests getRoutesByNames() returns exactly the requested routes.
   */
  public function testGetRoutesByNames(): void {
    $provider = $this->buildProvider($this->buildData());
    $result = $provider->getRoutesByNames(['a', 'missing', 'b']);
    $this->assertSame(['a', 'b'], array_keys($result));
  }

  /**
   * Tests getAllRoutes() iterates every compiled route.
   */
  public function testGetAllRoutes(): void {
    $provider = $this->buildProvider($this->buildData());
    $names = array_keys(iterator_to_array($provider->getAllRoutes()));
    sort($names);
    $this->assertSame(['a', 'b'], $names);
  }

  /**
   * Tests reset() clears the cached data and invalidates the 'routes' tag.
   */
  public function testReset(): void {
    $invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $invalidator->expects($this->once())
      ->method('invalidateTags')
      ->with(['routes']);
    $provider = $this->buildProvider($this->buildData(), $invalidator);
    $provider->reset();
  }

  /**
   * Tests preLoadRoutes() throws on empty input and is otherwise a no-op.
   */
  public function testPreLoadRoutes(): void {
    $provider = $this->buildProvider($this->buildData());
    $this->expectException(\InvalidArgumentException::class);
    $provider->preLoadRoutes([]);
  }

  /**
   * Tests getRouteAliases() returns Alias objects pointing at the target.
   */
  public function testGetRouteAliases(): void {
    $provider = $this->buildProvider($this->buildData());
    $aliases = iterator_to_array($provider->getRouteAliases('a'));
    $this->assertSame(['alias.a'], array_keys($aliases));
    $this->assertSame('a', $aliases['alias.a']->getId());
  }

  /**
   * Tests getRoutesByPattern() finds routes whose pattern outline matches.
   */
  public function testGetRoutesByPattern(): void {
    $collection = new RouteCollection();
    $collection->add('list', new Route('/entity/{type}/list'));
    $collection->add('other', new Route('/something/else'));
    $data = CompiledRouterData::fromCollection($collection);

    $provider = $this->buildProvider($data);
    $matches = $provider->getRoutesByPattern('/entity/{type}/list');
    $this->assertSame(['list'], array_keys($matches->all()));
  }

}
