<?php

namespace Drupal\router_route_callback_secondary_test\Routing;

use Symfony\Component\Routing\Route;

/**
 * Subscriber for Entity Test routes.
 */
class SecondaryRouterTestRoutes {

  /**
   * Returns an array of route objects.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of route objects.
   */
  public function routes() {
    $routes = [];
    $routes['route_callback.secondary.test'] = new Route(
      "/route_callback/secondary/test",
      ['_controller' => '\Drupal\router_route_callback_secondary_test\Controller\SecondaryTestController::test'],);

    return $routes;
  }

}
