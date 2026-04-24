<?php

declare(strict_types=1);

namespace Drupal\Core\Routing\RouteStorage;

use Symfony\Component\Routing\RouteCollection;

/**
 * Interface for loading, dumping, and invalidating compiled routes.
 *
 * The implementation is auto-detected from the 'route_storage_class' setting.
 * Sites that need a custom storage strategy can override the 'route_storage'
 * service in a service provider.
 *
 * @see \Drupal\Core\Routing\RouteStorage\PhpDumperRouteStorage
 * @see \Drupal\Core\Routing\RouteStorage\NullRouteStorage
 */
interface RouteStorageInterface {

  /**
   * Attempts to load a previously stored compiled router data set.
   *
   * @param string $suffix
   *   A hash suffix identifying the router version, computed by the kernel
   *   from the environment, enabled modules, etc.
   *
   * @return \Drupal\Core\Routing\RouteStorage\CompiledRouterData|null
   *   The loaded router data, or NULL if no valid cached data exists.
   */
  public function load(string $suffix): ?CompiledRouterData;

  /**
   * Dumps a compiled route collection to persistent storage.
   *
   * @param \Symfony\Component\Routing\RouteCollection $routes
   *   The final route collection to compile. Callers must have dispatched all
   *   static/dynamic/alter events and installed access checks before calling.
   * @param string $suffix
   *   A hash suffix identifying this router version.
   *
   * @return \Drupal\Core\Routing\RouteStorage\CompiledRouterData
   *   The compiled router data, suitable for immediate use in the same
   *   request.
   */
  public function dump(RouteCollection $routes, string $suffix): CompiledRouterData;

  /**
   * Invalidates all stored compiled route data.
   */
  public function invalidate(): void;

}
