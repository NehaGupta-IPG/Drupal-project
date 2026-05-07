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
 * - a name-indexed map of serialize()'d entries (Route or Alias); both types
 *   round-trip through serialize(), so a single map supports
 *   RouteProvider::getRouteByName()'s alias-resolution recursion unchanged;
 * - an alias-name => target-name index for cheap getRouteAliases() queries;
 * - an index grouping routes by pattern outline, so
 *   getRouteCollectionForRequest() can return every candidate for a given
 *   path without a full scan;
 * - the set of fit bit masks that actually correspond to routes.
 *
 * Entries are held serialized until requested; unserialize happens lazily.
 */
final class CompiledRouterData {

  /**
   * @param array<string, string> $serializedEntries
   *   Map of name to serialize()'d Route or Alias.
   * @param array<string, string> $aliases
   *   Map of alias name to target route name.
   * @param array<string, list<array{name: string, fit: int, numParts: int}>> $outlineIndex
   *   Pattern outlines mapped to the list of routes sharing that outline.
   * @param list<int> $numberPartsMasks
   *   The fit bit masks that are actually used by at least one route.
   */
  public function __construct(
    private readonly array $serializedEntries,
    private readonly array $aliases,
    private readonly array $outlineIndex,
    private readonly array $numberPartsMasks,
  ) {}

  /**
   * Returns the serialized entry (Route or Alias) for a name.
   *
   * Consumers (notably a RouteProvider::preLoadRoutes() override) pass the
   * result straight into their local serialized-routes cache.
   *
   * @return string|null
   *   The serialized entry, or NULL if the name is unknown.
   */
  public function getSerializedEntry(string $name): ?string {
    return $this->serializedEntries[$name] ?? NULL;
  }

  /**
   * Looks up a Route by name, following alias chains.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The resolved Route, or NULL if nothing is found. Aliases without a
   *   valid target resolve to NULL.
   */
  public function getRoute(string $name): ?Route {
    $seen = [];
    while (isset($this->aliases[$name])) {
      if (isset($seen[$name])) {
        // Alias cycle — bail out rather than loop forever.
        return NULL;
      }
      $seen[$name] = TRUE;
      $name = $this->aliases[$name];
    }
    if (!isset($this->serializedEntries[$name])) {
      return NULL;
    }
    $entry = unserialize($this->serializedEntries[$name]);
    return $entry instanceof Route ? $entry : NULL;
  }

  /**
   * Looks up multiple Routes by name, following alias chains.
   *
   * @param string[] $names
   *   The route names.
   *
   * @return array<string, \Symfony\Component\Routing\Route>
   *   Keyed by name. Names that resolve to nothing are omitted.
   */
  public function getRoutesByNames(array $names): array {
    $result = [];
    foreach ($names as $name) {
      $route = $this->getRoute($name);
      if ($route !== NULL) {
        $result[$name] = $route;
      }
    }
    return $result;
  }

  /**
   * Returns every known name (both Routes and Aliases).
   *
   * @return string[]
   *   The full name list.
   */
  public function getAllNames(): array {
    return array_keys($this->serializedEntries);
  }

  /**
   * Returns only the names that correspond to Route objects.
   *
   * @return string[]
   *   The route names (aliases excluded).
   */
  public function getRouteNames(): array {
    return array_keys(array_diff_key($this->serializedEntries, $this->aliases));
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
   * Returns the set of path-length bit masks in use.
   *
   * @return list<int>
   *   The fit bit masks, sorted in descending order.
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
   * Returns the full alias-name => target-name map.
   *
   * @return array<string, string>
   *   The alias map.
   */
  public function getAliases(): array {
    return $this->aliases;
  }

  /**
   * Serializes this data to an opcache-friendly array.
   *
   * Keys are stable and form the on-disk contract for the compiled file.
   *
   * @return array<string, mixed>
   *   The payload.
   */
  public function toPayload(): array {
    return [
      'entries' => $this->serializedEntries,
      'aliases' => $this->aliases,
      'outlines' => $this->outlineIndex,
      'masks' => $this->numberPartsMasks,
    ];
  }

  /**
   * Reconstructs a CompiledRouterData from a payload produced by toPayload().
   */
  public static function fromPayload(array $payload): self {
    return new self(
      $payload['entries'] ?? [],
      $payload['aliases'] ?? [],
      $payload['outlines'] ?? [],
      $payload['masks'] ?? [],
    );
  }

  /**
   * Builds a CompiledRouterData directly from a RouteCollection.
   *
   * Used when writing to disk and by NullRouteStorage to satisfy the current
   * request without writing anything. Routes are cloned before mutation to
   * avoid mutating collection state held by callers.
   */
  public static function fromCollection(RouteCollection $routes): self {
    $entries = [];
    $outlineIndex = [];
    $masks = [];

    foreach ($routes->all() as $name => $route) {
      // Prime each route with Drupal's RouteCompiler *before* serializing.
      // The Route's internal compile cache and options are included in
      // __serialize(), so setting compiler_class here ensures that when the
      // routes are unserialized later and a consumer calls $route->compile()
      // they get a Drupal CompiledRoute with getFit(), getPatternOutline(),
      // etc. Clone so the caller's collection remains pristine.
      $route = clone $route;
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

      $entries[$name] = serialize($route);
    }

    $aliases = [];
    foreach ($routes->getAliases() as $name => $alias) {
      // Serialize the full Alias object so parent RouteProvider's
      // deprecation-aware alias resolution in getRouteByName() still works.
      $entries[$name] = serialize($alias);
      $aliases[$name] = $alias->getId();
    }

    $masks = array_keys($masks);
    rsort($masks);

    return new self($entries, $aliases, $outlineIndex, $masks);
  }

}
