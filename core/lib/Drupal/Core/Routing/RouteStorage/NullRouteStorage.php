<?php

declare(strict_types=1);

namespace Drupal\Core\Routing\RouteStorage;

use Symfony\Component\Routing\RouteCollection;

/**
 * No-op route storage that never loads or persists compiled route data.
 *
 * Used in installer contexts and by kernel tests that do not want to write
 * router data to disk. ::dump() still returns a fully usable
 * CompiledRouterData so the current request can proceed; nothing is written.
 */
class NullRouteStorage implements RouteStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function load(string $suffix): ?CompiledRouterData {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function dump(RouteCollection $routes, string $suffix): CompiledRouterData {
    return CompiledRouterData::fromCollection($routes);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(): void {
    // No-op.
  }

}
