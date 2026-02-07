<?php

namespace Drupal\Core\Utility;

use Drupal\Component\Utility\OpenTelemetry;

/**
 * Helper functions to create fibers in a consistent way.
 */
class Async {

  /**
   * Creates and returns a new fiber.
   *
   * @param callable $callback
   *   Function to invoke when starting the fiber.
   *
   * @return \Fiber
   *   The fiber
   */
  public static function createFiber(callable $callback): \Fiber {
    return new \Fiber(OpenTelemetry::bindContext($callback));
  }

}
