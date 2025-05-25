<?php

declare(strict_types=1);

namespace Drupal\hook_collector_altered_services;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Service that decorates ServiceWithHook and has hook implementations.
 */
class DecoratedServiceWithHook {

  /**
   * Implements hook_test_hook_2().
   */
  #[Hook('test_hook_2')]
  public function testHook2(array &$invocations): void {
    // There is a test_hook_2 implementation in ServiceWithHook, but that should
    // not run and this one should when this class's module is installed.
    $key = get_class($this) . '::' . __FUNCTION__;
    $invocations[$key] ??= 0;
    $invocations[$key]++;
  }

  /**
   * Implements hook_test_hook_4().
   */
  #[Hook('test_hook_4')]
  public function testHook4(array &$invocations): void {
    $key = get_class($this) . '::' . __FUNCTION__;
    $invocations[$key] ??= 0;
    $invocations[$key]++;
  }

}
