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
   * @return callable(): T
   *   The bound closure if open telemetry is available, otherwise the original
   *   callback.
   *
   * @template T
   */
  public static function bindContext(callable $callback): callable {
    if (class_exists(Context::class)) {
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
    else {
      return $callback;
    }
  }

}
