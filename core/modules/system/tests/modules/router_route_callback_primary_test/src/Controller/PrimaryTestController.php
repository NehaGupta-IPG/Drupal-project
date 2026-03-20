<?php

namespace Drupal\router_route_callback_primary_test\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller routines for testing the route_callbacks.
 */
class PrimaryTestController extends ControllerBase {

  /**
   * Test function.
   */
  public function test() {
    return ['#markup' => "test-primary"];
  }

}
