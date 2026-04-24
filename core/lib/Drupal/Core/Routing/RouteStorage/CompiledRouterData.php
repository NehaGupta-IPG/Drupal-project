<?php

declare(strict_types=1);

namespace Drupal\Core\Routing\RouteStorage;

use Drupal\Core\Routing\RouteCompiler;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Value object holding a compiled router snapshot.
 *
 * Holds the data the routing stack needs at runtime:
 * - a name-indexed map of serialized Route objects;
 * - the alias map;
 * - an index grouping routes by pattern outline (so
 *   getRouteCollectionForRequest() can return every candidate for a given
 *   path without scanning the full route set);
 * - the set of path-length bitmasks that actually correspond to routes.
 *
 * Routes are held serialized until requested, so the initial materialisation
 * is cheap; unserialize happens lazily per route.
 */
final class CompiledRouterData {

  /**
   * Unserialized Route objects, keyed by name.
   *
   * @var array<string, \Symfony\Component\Routing\Route>
   */
  private array $routes = [];

  /**
   * @param array<string, string> $serializedRoutes
   *   A map of route name to serialize()d Route instance.
   * @param array<string, string> $aliases
   *   A map of alias name to target route name.
   * @param array<string, list<array{name: string, fit: int, numParts: int}>> $outlineIndex
   *   Pattern outlines mapped to the list of routes sharing that outline.
   * @param list<int> $numberPartsMasks
   *   The fit bitmasks that are actually used by at least one route.
   */
  public function __construct(
    private readonly array $serializedRoutes,
    private readonly array $aliases,
    private readonly array $outlineIndex,
    private readonly array $numberPartsMasks,
  ) {}

  /**
   * Looks up a Route by name, resolving aliases.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The Route, or NULL if it does not exist.
   */
  public function getRoute(string $name): ?Route {
    $name = $this->aliases[$name] ?? $name;
    if (!isset($this->serializedRoutes[$name])) {
      return NULL;
    }
    if (!isset($this->routes[$name])) {
      $this->routes[$name] = unserialize($this->serializedRoutes[$name]);
    }
    return $this->routes[$name];
  }

  /**
   * Looks up multiple Routes by name.
   *
   * @param string[] $names
   *   The route names.
   *
   * @return array<string, \Symfony\Component\Routing\Route>
   *   Keyed by name. Missing names are omitted.
   */
  public function getRoutesByNames(array $names): array {
    $result = [];
    foreach ($names as $name) {
      if ($route = $this->getRoute($name)) {
        $result[$name] = $route;
      }
    }
    return $result;
  }

  /**
   * Returns the names of every defined route (excluding aliases).
   *
   * @return string[]
   *   The route names.
   */
  public function getAllRouteNames(): array {
    return array_keys($this->serializedRoutes);
  }

  /**
   * Returns the raw name => serialize()'d Route map.
   *
   * @return array<string, string>
   *   Serialized route map, as stored on disk.
   *
   * @internal
   *   Exposed so storage implementations can dump the map without touching
   *   the unserialize path.
   */
  public function getSerializedRoutes(): array {
    return $this->serializedRoutes;
  }

  /**
   * Returns the outline-to-routes index.
   *
   * @return array<string, list<array{name: string, fit: int, numParts: int}>>
   *   The outline index, as stored on disk.
   *
   * @internal
   *   Exposed so storage implementations can dump the index.
   */
  public function getOutlineIndex(): array {
    return $this->outlineIndex;
  }

  /**
   * Returns the index entries for a given pattern outline.
   *
   * @return list<array{name: string, fit: int, numParts: int}>
   *   The index entries, or an empty list if no route has that outline.
   */
  public function getRoutesForOutline(string $outline): array {
    return $this->outlineIndex[$outline] ?? [];
  }

  /**
   * Returns the set of path-length bitmasks in use.
   *
   * @return list<int>
   *   The fit bitmasks, sorted in descending order.
   */
  public function getNumberPartsMasks(): array {
    return $this->numberPartsMasks;
  }

  /**
   * Returns the names of aliases pointing to a given route name.
   *
   * @return string[]
   *   The alias names.
   */
  public function getAliasNamesFor(string $routeName): array {
    return array_keys($this->aliases, $routeName, TRUE);
  }

  /**
   * Returns the alias map.
   *
   * @return array<string, string>
   *   Alias name keyed to target route name.
   */
  public function getAliases(): array {
    return $this->aliases;
  }

  /**
   * Builds a CompiledRouterData directly from a RouteCollection.
   *
   * Used when writing to disk and by NullRouteStorage to satisfy the current
   * request without writing anything. The collection is iterated twice:
   * routes are serialized for the name map, and each route is compiled with
   * Drupal's RouteCompiler so we can group by pattern outline.
   */
  public static function fromCollection(RouteCollection $routes): self {
    $serialized = [];
    $outlineIndex = [];
    $masks = [];

    // Prime each route with Drupal's RouteCompiler *before* serialising. The
    // Route's internal compile cache and options are included in __serialize,
    // so setting compiler_class here ensures that when the routes are
    // unserialised later and any consumer calls $route->compile() they get a
    // Drupal CompiledRoute with getFit(), getPatternOutline(), etc.
    foreach ($routes->all() as $name => $route) {
      $route->setOption('compiler_class', RouteCompiler::class);
      $compiled = RouteCompiler::compile($route);

      $outline = $compiled->getPatternOutline();
      $fit = (int) $compiled->getFit();
      $numParts = (int) $compiled->getNumParts();

      $outlineIndex[$outline][] = [
        'name' => $name,
        'fit' => $fit,
        'numParts' => $numParts,
      ];
      $masks[$fit] = TRUE;

      $serialized[$name] = serialize($route);
    }

    $aliases = [];
    foreach ($routes->getAliases() as $name => $alias) {
      $aliases[$name] = $alias->getId();
    }

    $masks = array_keys($masks);
    rsort($masks);

    return new self($serialized, $aliases, $outlineIndex, $masks);
  }

}
