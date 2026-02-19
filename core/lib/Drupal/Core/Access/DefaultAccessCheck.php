<?php

namespace Drupal\Core\Access;

use Drupal\Core\Routing\Access\AccessInterface as RoutingAccessInterface;
use Symfony\Component\Routing\Route;

/**
 * Allows access to routes to be controlled by an '_access' boolean parameter.
 */
class DefaultAccessCheck implements RoutingAccessInterface {

  /**
   * Checks access to the route based on the _access parameter.
   *
   * @param Route $route
   *   The route to check against.
   *
   * @return AccessResultInterface
   *   The access result.
   */
  public function access(Route $route) {
    if ($route->getRequirement('_access') === 'TRUE') {
      return AccessResult::allowed();
    }
    elseif ($route->getRequirement('_access') === 'FALSE') {
      return AccessResult::forbidden();
    }
    else {
      return AccessResult::neutral();
    }
  }

}
