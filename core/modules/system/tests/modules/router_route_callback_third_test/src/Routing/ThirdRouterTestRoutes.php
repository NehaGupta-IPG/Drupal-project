<?php

namespace Drupal\router_route_callback_third_test\Routing;

use Symfony\Component\Routing\Route;

/**
 * Subscriber for Entity Test routes.
 */
class ThirdRouterTestRoutes {

  /**
   * Returns an array of route objects.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of route objects.
   */
  public function routes() {
    $routes = [];
    $routes['route_callback.third.test'] = new Route(
      "/route_callback/third/test",
      ['_controller' => '\Drupal\router_route_callback_third_test\Controller\ThirdTestController::test'],);

    return $routes;
  }

}
