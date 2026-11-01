<?php

declare(strict_types=1);

namespace Drupal\bbb_hook_order_test\Hook;

use Drupal\aaa_hook_order_test\Hook\ABetweenHooks;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Hook\Order\OrderBefore;

/**
 * Provides hook implementations for testing the execution order of hooks.
 *
 * By default, these will be called in module order, which is predictable due to
 * the alphabetical module names.
 *
 * @see \Drupal\aaa_hook_order_test\Hook\ABetweenHooks
 * @see \Drupal\KernelTests\Core\Hook\HookOrderTest::testHookBetweenOrder()
 */
class BBetweenHooks {

  /**
   * Implements hook_test_between_hook().
   *
   * This implementation has no ordering modifications.
   */
  #[Hook('test_between_hook', order: new OrderAfter(classesAndMethods: [[ABetweenHooks::class, 'testBetweenHookOneA']]))]
  public function testBetweenHookOneB(): string {
    return __METHOD__;
  }

  /**
   * Implements hook_test_between_hook().
   *
   * This implementation changes its order to be first.
   */
  #[Hook('test_between_hook', order: new OrderBefore(classesAndMethods: [[ABetweenHooks::class, 'testBetweenHookTwoA']]))]
  public function testBetweenHookTwoB(): string {
    return __METHOD__;
  }

}
