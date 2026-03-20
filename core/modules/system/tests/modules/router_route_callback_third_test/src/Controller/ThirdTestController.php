<?php

namespace Drupal\router_route_callback_third_test\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller routines for testing the route_callbacks.
 */
class ThirdTestController extends ControllerBase {

  /**
   * Test function.
   */
  public function test() {
    return ['#markup' => "test-third"];
  }

}
