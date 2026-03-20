<?php

namespace Drupal\router_route_callback_primary_test\Routing;

use Symfony\Component\Routing\Route;

/**
 * Subscriber for Entity Test routes.
 */
class PrimaryRouterTestRoutes {

  /**
   * Returns an array of route objects.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of route objects.
   */
  public function routes() {
    $routes = [];
    $routes['route_callback.primary.test'] = new Route(
      "/route_callback/primary/test",
      ['_controller' => '\Drupal\router_route_callback_primary_test\Controller\PrimaryTestController::test'],
    );

    return $routes;
  }

}
