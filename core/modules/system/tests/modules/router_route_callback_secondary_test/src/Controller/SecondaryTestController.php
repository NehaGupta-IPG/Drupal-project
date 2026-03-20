<?php

namespace Drupal\router_route_callback_secondary_test\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller routines for testing the route_callbacks.
 */
class SecondaryTestController extends ControllerBase {

  /**
   * Test function.
   */
  public function test() {
    return ['#markup' => "test-secondary"];
  }

}
