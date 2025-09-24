<?php

declare(strict_types=1);

namespace Drupal\hook_collector_altered_services;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\hook_collector_hook_attribute\AnotherServiceWithHook;

/**
 * Class that replaces AnotherServiceWithHook in service definition.
 *
 * This class does not override ::testHook3() from the parent class, so that
 * method should run once when test_hook_3 is invoked.
 */
class AlteredServiceWithHook extends AnotherServiceWithHook {

  /**
   * Implements hook_test_hook_4().
   */
  #[Hook('test_hook_4')]
  public function testHook4(array &$invocations): void {
    // This is a hook implementation that does not exist in the parent class.
    $key = get_class($this) . '::' . __FUNCTION__;
    $invocations[$key] ??= 0;
    $invocations[$key]++;
  }

  /**
   * {@inheritdoc}
   */
  public function testHook6(array &$invocations): void {
    // This overrides the parent method with the Hook attribute removed. This
    // should not be run when test_hook_7 is invoked.
    $key = get_class($this) . '::' . __FUNCTION__;
    $invocations[$key] ??= 0;
    $invocations[$key]++;
  }

}
