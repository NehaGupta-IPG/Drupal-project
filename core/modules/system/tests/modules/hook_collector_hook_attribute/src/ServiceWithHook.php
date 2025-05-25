<?php

declare(strict_types=1);

namespace Drupal\hook_collector_hook_attribute;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Service class with Hook attribute.
 *
 * This is used to test that any service registered in the module's services.yml
 * can have hook implementations, regardless of where under the module's
 * namespace it exists.
 */
class ServiceWithHook {

  /**
   * Implements hook_test_hook_2().
   */
  #[Hook('test_hook_2')]
  public function testHook2(array &$invocations): void {
    $key = get_class($this) . '::' . __FUNCTION__;
    $invocations[$key] ??= 0;
    $invocations[$key]++;
  }

  /**
   * Implements hook_test_hook_5().
   */
  #[Hook('test_hook_5')]
  public function testHook5(array &$invocations): void {
    $key = get_class($this) . '::' . __FUNCTION__;
    $invocations[$key] ??= 0;
    $invocations[$key]++;
  }

}
