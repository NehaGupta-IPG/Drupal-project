<?php

namespace Drupal\Core\Routing;

use Symfony\Component\HttpFoundation\Request;

/**
 * A Route Provider front-end for all Drupal-stored routes.
 */
class RouteProviderLazyBuilder implements PreloadableRouteProviderInterface {

  public function __construct(
    protected RouteProviderInterface $routeProvider,
    protected RouteBuilderInterface $routeBuilder,
    protected RouteProviderLazyBuilderSubscriber $rebuildState,
  ) {}

  /**
   * Rebuilds the router if marked stale and returns the storage provider.
   */
  protected function getRouteProvider(): RouteProviderInterface {
    if (!$this->rebuildState->hasRebuilt() && !$this->rebuildState->isRebuilding()) {
      $this->routeBuilder->rebuild();
    }
    return $this->routeProvider;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteCollectionForRequest(Request $request) {
    return $this->getRouteProvider()->getRouteCollectionForRequest($request);
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteByName($name) {
    return $this->getRouteProvider()->getRouteByName($name);
  }

  /**
   * {@inheritdoc}
   */
  public function preLoadRoutes($names) {
    return $this->getRouteProvider()->preLoadRoutes($names);
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutesByNames($names) {
    return $this->getRouteProvider()->getRoutesByNames($names);
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutesByPattern($pattern) {
    return $this->getRouteProvider()->getRoutesByPattern($pattern);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllRoutes() {
    return $this->getRouteProvider()->getAllRoutes();
  }

  /**
   * {@inheritdoc}
   */
  public function reset() {
    // Don't call getRouteProvider as this results in recursive rebuilds.
    return $this->routeProvider->reset();
  }

  /**
   * Determines if the router has been rebuilt.
   */
  public function hasRebuilt(): bool {
    return $this->rebuildState->hasRebuilt();
  }

  /**
   * Marks the routing table as stale so the next lookup triggers a rebuild.
   *
   * ModuleInstaller calls this when the set of installed modules is about to
   * change, so that any route lookups performed during install/uninstall hooks
   * see routes for the new module list.
   */
  public function markStale(): void {
    $this->rebuildState->markStale();
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteAliases(string $route_name): iterable {
    return $this->getRouteProvider()->getRouteAliases($route_name);
  }

}
