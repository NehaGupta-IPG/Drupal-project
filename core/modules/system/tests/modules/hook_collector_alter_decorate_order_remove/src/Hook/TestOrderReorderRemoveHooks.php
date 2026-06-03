<?php

declare(strict_types=1);

namespace Drupal\hook_collector_alter_decorate_order_remove\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Attribute\RemoveHook;
use Drupal\Core\Hook\Attribute\ReorderHook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Hook\Order\OrderBefore;
use Drupal\hook_collector_alter_decorate_target\Hook\TestHooks;

/**
 * Provides hooks that target other hooks that have been altered or decorated.
 */
class TestOrderReorderRemoveHooks {

  #[Hook('test_method_order', order: new OrderAfter(classesAndMethods: [[TestHooks::class, 'testMethodOrder']]))]
  public function testMethodOrderAfter(&$variables): void {
    $variables[__METHOD__] = TRUE;
  }

  #[Hook('test_method_order', order: new OrderBefore(classesAndMethods: [[TestHooks::class, 'testMethodOrder']]))]
  public function testMethodOrderBefore(&$variables): void {
    $variables[__METHOD__] = TRUE;
  }

  #[Hook('test_method_reorder')]
  #[ReorderHook(
    hook: 'test_method_reorder',
    class: TestHooks::class,
    method: 'testMethodReorder',
    order: new OrderBefore(classesAndMethods: [[self::class, 'reorderHook']]))]
  public function reorderHook(array &$variables): void {
    $variables[__METHOD__] = TRUE;
  }

  #[RemoveHook('test_method_remove', TestHooks::class, 'testMethodRemove')]
  public function removeHook(): void {}

}
