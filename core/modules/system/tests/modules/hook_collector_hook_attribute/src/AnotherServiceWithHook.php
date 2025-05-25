<?php

declare(strict_types=1);

namespace Drupal\hook_collector_hook_attribute;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Another service class with Hook attribute.
 *
 * This is used to test that any service registered in the module's services.yml
 * can have hook implementations, regardless of where under the module's
 * namespace it exists.
 */
class AnotherServiceWithHook {

  use HookInvocationsCountTrait;

  /**
   * Implements hook_test_hook_3().
   */
  #[Hook('test_hook_3')]
  public function testHook3(array &$invocations): void {
    $this->incrementRunCount(get_class($this) . '::' . __FUNCTION__, $invocations);
  }

  /**
   * Implements hook_test_hook_6().
   */
  #[Hook('test_hook_6')]
  public function testHook6(array &$invocations): void {
    $this->incrementRunCount(get_class($this) . '::' . __FUNCTION__, $invocations);
  }

}
