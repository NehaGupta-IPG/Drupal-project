<?php

declare(strict_types=1);

namespace Drupal\aaa_hook_order_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;

/**
 * This class contains hook implementations.
 *
 * By default, these will be called in module order, which is predictable due
 * to the alphabetical module names. Some of the implementations are reordered
 * using order attributes.
 */
class AAlterHooks {

  #[Hook('test_alter', order: new OrderAfter(modules: ['ccc_hook_order_test']))]
  public function testAlterAfterC(array &$calls): void {
    $calls[] = __METHOD__;
  }

  #[Hook('test_subtype_alter')]
  public function testSubtypeAlter(array &$calls): void {
    $calls[] = __METHOD__;
  }

  #[Hook('test_tomato_alter')]
  #[Hook('test_tomato_alter')]
  public function testTomatoTomatoAlter(array &$calls): void {
    $calls[] = __METHOD__;
  }

  #[Hook('test_apple_alter')]
  #[Hook('test_orange_alter')]
  public function testAppleOrangeAlter(array &$calls): void {
    $calls[] = __METHOD__;
  }

  #[Hook('test_moth_alter')]
  #[Hook('test_butterfly_alter', module: 'bbb_hook_order_test')]
  public function testMothButterflyAlter(array &$calls): void {
    $calls[] = __METHOD__;
  }

}
