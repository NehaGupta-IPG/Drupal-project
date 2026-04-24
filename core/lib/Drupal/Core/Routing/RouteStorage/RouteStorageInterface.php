<?php

declare(strict_types=1);

namespace Drupal\Core\Routing\RouteStorage;

use Symfony\Component\Routing\RouteCollection;

/**
 * Interface for loading, dumping, and invalidating compiled routes.
 *
 * Implementations are responsible for their own cache-key derivation;
 * callers do not supply one.
 *
 * @see \Drupal\Core\Routing\RouteStorage\PhpDumperRouteStorage
 * @see \Drupal\Core\Routing\RouteStorage\NullRouteStorage
 */
interface RouteStorageInterface {

  /**
   * Attempts to load the compiled router data for this site.
   *
   * @return \Drupal\Core\Routing\RouteStorage\CompiledRouterData|null
   *   The loaded router data, or NULL if no valid cached data exists.
   */
  public function load(): ?CompiledRouterData;

  /**
   * Dumps a compiled route collection to persistent storage.
   *
   * @param \Symfony\Component\Routing\RouteCollection $routes
   *   The final route collection to compile. Callers must have dispatched all
   *   static/dynamic/alter events and installed access checks before calling.
   *
   * @return \Drupal\Core\Routing\RouteStorage\CompiledRouterData
   *   The compiled router data, suitable for immediate use in the same
   *   request.
   */
  public function dump(RouteCollection $routes): CompiledRouterData;

  /**
   * Invalidates all stored compiled route data.
   */
  public function invalidate(): void;

}
