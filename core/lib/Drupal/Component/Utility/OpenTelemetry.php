<?php

namespace Drupal\Component\Utility;

use OpenTelemetry\Context\Context;

/**
 * Provides helpers for open telemetry.
 *
 * @ingroup utility
 */
class OpenTelemetry {

  /**
   * Binds the given callback to the current open telemetry context.
   *
   * @param callable(): T $callback
   *   The callback to be bound.
   *
   * @return \Closure(): T
   *   The bound closure.
   *
   * @template T
   */
  public static function bindContext(callable $callback): \Closure {
    $parent = Context::getCurrent();
    return static function (mixed ...$args) use ($parent, $callback) {
      $scope = Context::storage()->attach($parent);
      try {
        return $callback(...$args);
      }
      finally {
        $scope->detach();
      }
    };
  }

}
