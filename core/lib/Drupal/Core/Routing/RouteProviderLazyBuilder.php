<?php

namespace Drupal\Core\Routing;

use Symfony\Component\HttpFoundation\Request;

/**
 * A Route Provider front-end for all Drupal-stored routes.
 *
 * @deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. The
 *   'router.route_provider' service now rebuilds routes on demand itself; use
 *   \Drupal\Core\Routing\RouteProvider::markStale() instead.
 *
 * @see https://www.drupal.org/node/9999999
 */
class RouteProviderLazyBuilder implements PreloadableRouteProviderInterface {

  /**
   * The route provider service.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The route building service.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routeBuilder;

  /**
   * Flag to determine if the router has been rebuilt.
   *
   * @var bool
   */
  protected $rebuilt = FALSE;

  /**
   * Flag to determine if router is currently being rebuilt.
   *
   * Used to prevent recursive router rebuilds during module installation.
   * Recursive rebuilds can occur when route information is required by alter
   * hooks that are triggered during a rebuild, for example,
   * hook_menu_links_discovered_alter().
   *
   * @var bool
   */
  protected $rebuilding = FALSE;

  /**
   * RouteProviderLazyBuilder constructor.
   *
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider service.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $route_builder
   *   The route building service.
   */
  public function __construct(RouteProviderInterface $route_provider, RouteBuilderInterface $route_builder) {
    // phpcs:ignore Drupal.Semantics.FunctionTriggerError
    @trigger_error(__CLASS__ . ' is deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. The router.route_provider service now rebuilds routes on demand itself. See https://www.drupal.org/node/9999999', E_USER_DEPRECATED);
    $this->routeProvider = $route_provider;
    $this->routeBuilder = $route_builder;
  }

  /**
   * Gets the real route provider service and rebuilds the router id necessary.
   *
   * @return \Drupal\Core\Routing\RouteProviderInterface
   *   The route provider service.
   */
  protected function getRouteProvider() {
    if (!$this->rebuilt && !$this->rebuilding) {
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
    // Don't call getRouteProvider as this is results in recursive rebuilds.
    return $this->routeProvider->reset();
  }

  /**
   * Determines if the router has been rebuilt.
   *
   * @return bool
   *   TRUE is the router has been rebuilt, FALSE if not.
   */
  public function hasRebuilt() {
    return $this->rebuilt;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteAliases(string $route_name): iterable {
    return $this->getRouteProvider()->getRouteAliases($route_name);
  }

}
