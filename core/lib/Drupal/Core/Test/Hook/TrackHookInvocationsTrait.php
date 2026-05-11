<?php

declare(strict_types=1);

namespace Drupal\Core\Test\Hook;

/**
 * Trait that provides a method to track many times a method has been invoked.
 */
trait TrackHookInvocationsTrait {

  /**
   * Tracks invocations of a method.
   *
   * @param array<string, int> $invocations
   *   An array of the number of times a method has been invoked keyed by
   *   {class}::{method}.
   * @param string $name
   *   The name of the method invoked.
   */
  public function trackInvocation(array &$invocations, string $name): void {
    $key = get_class($this) . '::' . $name;
    $invocations[$key] ??= 0;
    $invocations[$key]++;
  }

}
