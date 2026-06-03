<?php

declare(strict_types=1);

namespace Drupal\hook_collector_decorate_hook_class;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Decorates Drupal\hook_collector_alter_decorate_target\Hook.
 */
class TestHooksDecorated {

  /**
   * Implements a hook method that will be replaced by a different class method.
   */
  #[Hook('test_method_replaced')]
  public function testMethodReplaced(array &$variables): void {
    $variables[__METHOD__] = TRUE;
  }

  /**
   * Implements a hook method targeted by the order parameter of another method.
   */
  #[Hook('test_method_order')]
  public function testMethodOrder(array &$variables): void {
    $variables[__METHOD__] = TRUE;
  }

  #[Hook('test_method_reorder')]
  public function testMethodReorder(array &$variables): void {
    $variables[__METHOD__] = TRUE;
  }

  #[Hook('test_method_remove')]
  public function testMethodRemove(array &$variables): void {
    $variables[__METHOD__] = TRUE;
  }

}
