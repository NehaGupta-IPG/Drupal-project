<?php

declare(strict_types=1);

namespace Drupal\Core\Routing;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Routing\RouteStorage\CompiledRouterData;
use Drupal\Core\Routing\RouteStorage\RouteStorageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Alias;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route provider backed by a compiled-PHP route storage.
 *
 * Loads a CompiledRouterData from a RouteStorageInterface implementation and
 * answers all RouteProviderInterface requests from it, using Symfony's
 * CompiledUrlMatcher for request matching and an in-memory map for
 * name-based lookups.
 */
class CompiledRouteProvider implements CacheableRouteProviderInterface, PreloadableRouteProviderInterface, EventSubscriberInterface {

  /**
   * Cached router data for this request.
   */
  protected ?CompiledRouterData $data = NULL;

  /**
   * Extra cache key parts registered by the language/query providers.
   *
   * @var string[]
   */
  protected array $extraCacheKeyParts = [];

  public function __construct(
    protected readonly RouteStorageInterface $storage,
    protected readonly DrupalKernelInterface $kernel,
    protected readonly CurrentPathStack $currentPath,
    protected readonly CacheBackendInterface $cache,
    protected readonly InboundPathProcessorInterface $pathProcessor,
    protected readonly CacheTagsInvalidatorInterface $cacheTagInvalidator,
    protected readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Loads (or returns) the compiled router data for this request.
   */
  protected function getData(): ?CompiledRouterData {
    if ($this->data === NULL) {
      $this->data = $this->storage->load($this->kernel->getRoutesCacheKeySuffix());
    }
    return $this->data;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteByName($name) {
    $data = $this->getData();
    if ($data === NULL) {
      throw new RouteNotFoundException(sprintf('Route "%s" does not exist.', $name));
    }
    $route = $data->getRoute($name);
    if ($route === NULL) {
      throw new RouteNotFoundException(sprintf('Route "%s" does not exist.', $name));
    }
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutesByNames($names) {
    $data = $this->getData();
    if ($data === NULL) {
      return [];
    }
    return $data->getRoutesByNames($names);
  }

  /**
   * {@inheritdoc}
   */
  public function preLoadRoutes($names): void {
    // No-op: all routes are already loaded from the compiled file. This is
    // only retained for interface compatibility.
    if (empty($names)) {
      throw new \InvalidArgumentException('You must specify the route names to load');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteCollectionForRequest(Request $request) {
    // Mirror RouteProvider::getRouteCollectionForRequest()'s caching behaviour
    // so downstream code (e.g. language negotiation) observes identical keys.
    $cid = $this->getRouteCollectionCacheId($request);
    if ($cached = $this->cache->get($cid)) {
      $this->currentPath->setPath($cached->data['path'], $request);
      $request->query->replace($cached->data['query']);
      if ($cached->data['routes'] === FALSE) {
        return new RouteCollection();
      }
      return $cached->data['routes'];
    }

    $path = $request->getPathInfo();
    $path = $path === '/' ? $path : rtrim($path, '/');
    $path = $this->pathProcessor->processInbound($path, $request);
    $this->currentPath->setPath($path, $request);
    $queryParameters = $request->query->all();

    $routes = $this->matchPath($path, $request);
    $cacheValue = [
      'path' => $path,
      'query' => $queryParameters,
      'routes' => $routes->count() === 0 ? FALSE : $routes,
    ];
    $this->cache->set($cid, $cacheValue, CacheBackendInterface::CACHE_PERMANENT, ['route_match']);
    return $routes;
  }

  /**
   * Returns every route whose pattern outline could match the given path.
   *
   * Mirrors RouteProvider::getRoutesByPath() but sources its data from the
   * compiled outline index instead of a SQL query: for each candidate
   * outline derived from the request path, look up all routes sharing that
   * outline and collect them. Matches are returned sorted by fit (most
   * specific first, ties broken by name).
   */
  protected function matchPath(string $path, Request $request): RouteCollection {
    $collection = new RouteCollection();
    $data = $this->getData();
    if ($data === NULL) {
      return $collection;
    }

    $parts = preg_split('@/+@', mb_strtolower($path), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $numberParts = count($parts);
    $outlines = static::getCandidateOutlines($parts, $data->getNumberPartsMasks());
    if (empty($outlines)) {
      return $collection;
    }

    $candidates = [];
    foreach ($outlines as $outline) {
      foreach ($data->getRoutesForOutline($outline) as $entry) {
        if ($entry['numParts'] < $numberParts) {
          continue;
        }
        $route = $data->getRoute($entry['name']);
        if ($route === NULL) {
          continue;
        }
        $candidates[] = [
          'name' => $entry['name'],
          'route' => $route,
          'fit' => $entry['fit'],
        ];
      }
    }

    usort($candidates, static function (array $a, array $b): int {
      if ($a['fit'] === $b['fit']) {
        return strcmp($a['name'], $b['name']);
      }
      return (int) $b['fit'] <=> (int) $a['fit'];
    });

    foreach ($candidates as $candidate) {
      $collection->add($candidate['name'], $candidate['route']);
    }

    return $collection;
  }

  /**
   * Generates candidate pattern outlines for a path's parts.
   *
   * Returns the outlines that could match the given path, filtered through
   * the fit-bitmask set supplied by the compiled data so we only probe
   * shapes that actually exist.
   *
   * @param string[] $parts
   *   The path exploded on '/', with empty segments removed.
   * @param list<int> $masks
   *   The set of fit bitmasks in use across the route set.
   *
   * @return string[]
   *   The candidate outlines.
   */
  protected static function getCandidateOutlines(array $parts, array $masks): array {
    $numberParts = count($parts);
    if ($numberParts === 0) {
      return [];
    }
    $end = (1 << $numberParts) - 1;
    if ($numberParts === 1) {
      $candidateMasks = [1];
    }
    elseif ($numberParts <= 3) {
      $candidateMasks = range($end, 1);
    }
    else {
      $candidateMasks = $masks;
    }

    $length = $numberParts - 1;
    $outlines = [];
    foreach ($candidateMasks as $mask) {
      if ($mask > $end) {
        continue;
      }
      if ($mask < (1 << $length)) {
        --$length;
      }
      $current = '';
      for ($j = $length; $j >= 0; $j--) {
        $current .= ($mask & (1 << $j)) ? $parts[$length - $j] : '%';
        if ($j) {
          $current .= '/';
        }
      }
      $outlines[] = '/' . $current;
    }
    return $outlines;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutesByPattern($pattern) {
    return $this->getRoutesByPath($pattern);
  }

  /**
   * Finds all routes whose pattern outline matches the given path.
   *
   * This is the cold-path equivalent of RouteProvider::getRoutesByPath(): it
   * is used by a handful of callers (notably Views local task derivatives)
   * during cache warm-up, so the linear walk over all routes is acceptable.
   *
   * Matching uses the pattern outline so the provider works regardless of
   * the compiler class attached to each Route.
   */
  protected function getRoutesByPath(string $path): RouteCollection {
    $collection = new RouteCollection();
    $data = $this->getData();
    if ($data === NULL) {
      return $collection;
    }

    $outline = RouteCompiler::getPatternOutline($path);
    $candidates = [];
    foreach ($data->getAllRouteNames() as $name) {
      $route = $data->getRoute($name);
      if ($route === NULL) {
        continue;
      }
      if (RouteCompiler::getPatternOutline($route->getPath()) === $outline) {
        $fit = $this->getCompiledFit($route);
        $candidates[] = [
          'name' => $name,
          'route' => $route,
          'fit' => $fit,
        ];
      }
    }

    usort($candidates, static function (array $a, array $b): int {
      if ($a['fit'] === $b['fit']) {
        return strcmp($a['name'], $b['name']);
      }
      return (int) $b['fit'] <=> (int) $a['fit'];
    });

    foreach ($candidates as $candidate) {
      $collection->add($candidate['name'], $candidate['route']);
    }

    return $collection;
  }

  /**
   * Returns the Drupal fit value for a route, or 0 if unavailable.
   */
  protected function getCompiledFit(Route $route): int {
    $compiled = $route->compile();
    if ($compiled instanceof CompiledRoute) {
      return (int) $compiled->getFit();
    }
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllRoutes() {
    $data = $this->getData();
    if ($data === NULL) {
      return new \ArrayIterator();
    }
    $result = [];
    foreach ($data->getAllRouteNames() as $name) {
      $route = $data->getRoute($name);
      if ($route !== NULL) {
        $result[$name] = $route;
      }
    }
    return (new \ArrayObject($result))->getIterator();
  }

  /**
   * {@inheritdoc}
   */
  public function reset(): void {
    $this->data = NULL;
    $this->cacheTagInvalidator->invalidateTags(['routes']);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[RoutingEvents::FINISHED][] = ['reset'];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function addExtraCacheKeyPart($cache_key_provider, $cache_key_part): void {
    $this->extraCacheKeyParts[$cache_key_provider] = $cache_key_part;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteAliases(string $route_name): iterable {
    $data = $this->getData();
    if ($data === NULL) {
      return [];
    }
    $result = [];
    foreach ($data->getAliasNamesFor($route_name) as $aliasName) {
      // Rehydrating a live Alias object requires walking the underlying
      // RouteCollection, which we do not retain post-compile. Return a
      // structurally equivalent Alias that points at the same target. Callers
      // (RouteProvider::getRouteByName()) only consume ::getId() and
      // deprecation metadata.
      $result[$aliasName] = new Alias($route_name);
    }
    return $result;
  }

  /**
   * Builds the cache ID for per-request route collection caching.
   */
  protected function getRouteCollectionCacheId(Request $request): string {
    $this->addExtraCacheKeyPart('language', $this->getCurrentLanguageCacheIdPart());
    $this->addExtraCacheKeyPart('query_parameters', $this->getQueryParametersCacheIdPart($request));

    ksort($this->extraCacheKeyParts);
    $keyParts = [];
    foreach ($this->extraCacheKeyParts as $provider => $part) {
      $keyParts[] = '[' . $provider . ']=' . $part;
    }
    return 'route:' . implode(':', $keyParts) . ':' . $request->getPathInfo();
  }

  /**
   * Returns the current language identifier for the cache ID.
   */
  protected function getCurrentLanguageCacheIdPart(): string {
    return $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_URL)->getId();
  }

  /**
   * Returns the normalised query parameters for the cache ID.
   */
  protected function getQueryParametersCacheIdPart(Request $request): string {
    $recursiveSort = function (array &$array) use (&$recursiveSort): void {
      foreach ($array as &$v) {
        if (is_array($v)) {
          $recursiveSort($v);
        }
      }
      ksort($array);
    };

    $sortedResolved = $request->query->all();
    $recursiveSort($sortedResolved);
    $sortedOriginal = Request::create('/?' . $request->getQueryString())->query->all();
    $recursiveSort($sortedOriginal);

    $resolvedHash = $sortedResolved ? sha1(http_build_query($sortedResolved)) : NULL;

    return implode(',', array_filter([
      http_build_query($sortedOriginal),
      $resolvedHash,
    ]));
  }

}
