<?php

declare(strict_types=1);

namespace Drupal\Core\Routing;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Routing\RouteStorage\CompiledRouterData;
use Drupal\Core\Routing\RouteStorage\RouteStorageInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route provider backed by a compiled-PHP route storage.
 *
 * Extends the database-backed RouteProvider purely for code reuse — the
 * caching/cache-ID helpers, the Alias-aware alias resolution, and the
 * event-subscriber wiring are identical. The subclass overrides the
 * handful of methods that actually read from the database so they instead
 * consume a CompiledRouterData snapshot loaded via a RouteStorageInterface.
 *
 * RouteProvider's protected properties are untyped, so the child constructor
 * skips parent::__construct() and only assigns the subset we actually use.
 * Parent properties left uninitialized (connection, state, tableName) are
 * never read from, because every code path that would read them has been
 * overridden here.
 *
 * When the storage returns NULL (first request after install, or any
 * multi-web-head scenario where this head's local PhpStorage does not yet
 * hold the file referenced by the shared cache entry) the provider
 * triggers RouteBuilder::rebuild() to re-dump and re-load locally. A
 * rebuild flag prevents re-entering the build from within event
 * subscribers that look up routes during that same build.
 */
class CompiledRouteProvider extends RouteProvider {

  /**
   * TRUE while a rebuild is in progress, to prevent re-entry.
   */
  protected bool $rebuilding = FALSE;

  /**
   * TRUE once a miss-triggered rebuild has been attempted this request.
   */
  protected bool $rebuildAttempted = FALSE;

  public function __construct(
    protected readonly RouteStorageInterface $storage,
    protected readonly RouteBuilderInterface $routeBuilder,
    CurrentPathStack $currentPath,
    CacheBackendInterface $cache,
    InboundPathProcessorInterface $pathProcessor,
    CacheTagsInvalidatorInterface $cacheTagInvalidator,
    LanguageManagerInterface $languageManager,
  ) {
    // Deliberately skip parent::__construct(): RouteProvider's constructor
    // requires a Connection and StateInterface that this subclass does not
    // use, because every method that would read them is overridden here.
    // Set only the parent's properties our inherited code paths reference.
    $this->currentPath = $currentPath;
    $this->cache = $cache;
    $this->pathProcessor = $pathProcessor;
    $this->cacheTagInvalidator = $cacheTagInvalidator;
    $this->languageManager = $languageManager;
  }

  /**
   * Loads the compiled router data for this request.
   */
  protected function getData(): ?CompiledRouterData {
    $data = $this->storage->load();
    if ($data === NULL && !$this->rebuilding && !$this->rebuildAttempted) {
      $this->rebuildAttempted = TRUE;
      $this->rebuilding = TRUE;
      try {
        $this->routeBuilder->rebuild();
        $data = $this->storage->load();
      }
      finally {
        $this->rebuilding = FALSE;
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   *
   * Populates the parent's in-memory serializedRoutes cache straight from
   * the compiled snapshot — no DB query, no cache-backend round trip.
   */
  public function preLoadRoutes($names): void {
    if (empty($names)) {
      throw new \InvalidArgumentException('You must specify the route names to load');
    }
    $data = $this->getData();
    if ($data === NULL) {
      return;
    }
    $toLoad = array_diff($names, array_keys($this->routes), array_keys($this->serializedRoutes));
    foreach ($toLoad as $name) {
      $entry = $data->getSerializedEntry($name);
      if ($entry !== NULL) {
        $this->serializedRoutes[$name] = $entry;
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Uses the outline index from the compiled snapshot in place of the
   * legacy router-table query. Mirrors the legacy sort semantics (most
   * specific fit first, ties broken by name).
   */
  protected function getRoutesByPath($path) {
    $collection = new RouteCollection();
    $data = $this->getData();
    if ($data === NULL) {
      return $collection;
    }

    $parts = preg_split('@/+@', mb_strtolower($path), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $numberParts = count($parts);
    $outlines = $this->getCandidateOutlines($parts);
    if (empty($outlines)) {
      return $collection;
    }

    $candidates = [];
    foreach ($outlines as $outline) {
      foreach ($data->getRoutesForOutline($outline) as $entry) {
        if ($entry['numParts'] < $numberParts) {
          continue;
        }
        $candidates[$entry['name']] = $entry;
      }
    }

    usort($candidates, [$this, 'routeProviderRouteCompare']);

    $names = array_column($candidates, 'name');
    if (!empty($names)) {
      $this->preLoadRoutes($names);
    }
    foreach ($names as $name) {
      if (isset($this->serializedRoutes[$name])) {
        $this->routes[$name] = unserialize($this->serializedRoutes[$name]);
        unset($this->serializedRoutes[$name]);
      }
      if (isset($this->routes[$name])) {
        $collection->add($name, $this->routes[$name]);
      }
    }

    return $collection;
  }

  /**
   * {@inheritdoc}
   *
   * Sourced from the compiled bitmask set instead of the routing.menu_masks
   * state entry.
   */
  protected function getCandidateOutlines(array $parts) {
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
      $candidateMasks = $this->getData()?->getNumberPartsMasks() ?? [];
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
   *
   * @todo Large route sets pay a per-call unserialize tax here. Consider
   *   returning a generator that unserializes on iteration.
   */
  public function getAllRoutes() {
    $data = $this->getData();
    if ($data === NULL) {
      return new \ArrayIterator();
    }
    $routes = [];
    foreach ($data->getRouteNames() as $name) {
      $entry = $data->getSerializedEntry($name);
      if ($entry !== NULL) {
        $routes[$name] = unserialize($entry);
      }
    }
    return (new \ArrayObject($routes))->getIterator();
  }

  /**
   * {@inheritdoc}
   */
  public function reset(): void {
    $this->rebuildAttempted = FALSE;
    parent::reset();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // reset() must run at a higher priority than MenuRouterRebuildSubscriber
    // (which runs at 100) to ensure that the route data is cleared before it
    // is queried during the same rebuild.
    $events[RoutingEvents::FINISHED][] = ['reset', 200];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteAliases(string $route_name): iterable {
    $data = $this->getData();
    if ($data === NULL) {
      return [];
    }
    $aliasNames = $data->getAliasNamesFor($route_name);
    if (empty($aliasNames)) {
      return [];
    }
    return $this->getRoutesByNames($aliasNames);
  }

}
