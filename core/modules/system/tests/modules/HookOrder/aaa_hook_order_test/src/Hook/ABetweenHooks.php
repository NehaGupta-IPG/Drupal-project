<?php

declare(strict_types=1);

namespace Drupal\aaa_hook_order_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Provides hook implementations for testing the execution order of hooks.
 *
 * By default, these will be called in module order, which is predictable due to
 * the alphabetical module names.
 *
 * Two attributes are used to change the 'test_hook' implementations in module
 * bbb_hook_order_test. One is the OrderAfter parameter which is used to put
 * BBetweenHooks::testBetweenHookOneB() after
 * ABetweenHooks::testBetweenHookOneA. The other is the OrderBefore parameter
 * which is used to put BBetweenHooks::testBetweenHookTwoB() before
 * ABetweenHooks::testBetweenHookTwoA. Both of those attributes are declared in
 * \Drupal\bbb_hook_order_test\Hook\BBetweenHooks.
 *
 * @see \Drupal\KernelTests\Core\Hook\HookOrderTest::testHookBetweenOrder()
 */
class ABetweenHooks {

  /**
   * Implements hook_test_between_hook().
   *
   * This implementation has no ordering modifications.
   */
  #[Hook('test_between_hook')]
  public function testBetweenHookOneA(): string {
    return __METHOD__;
  }

  /**
   * Implements hook_test_between_hook().
   *
   * This implementation changes its order to be first.
   */
  #[Hook('test_between_hook')]
  public function testBetweenHookTwoA(): string {
    return __METHOD__;
  }

}
