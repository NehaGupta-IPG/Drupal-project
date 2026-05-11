<?php

declare(strict_types=1);

namespace Drupal\Core\Test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderBefore;
use Drupal\hook_collector_hook_attribute\Hook\HookRegisteredService;

/**
 * A test service that implements a hook.
 */
class TestHookService {

  use TrackHookInvocationsTrait;

  /**
   * Implements hook_test_hook_1().
   */
  #[Hook(
    hook: 'test_hook_1',
    order: new OrderBefore(classesAndMethods: [[HookRegisteredService::class, '__invoke']]),
  )]
  public function testHook1(array &$invocations): void {
    $this->trackInvocation($invocations, __FUNCTION__);
  }

  /**
   * Implements hook_test_hook_alter().
   */
  #[Hook('test_hook_alter')]
  public function testHookAlter(array &$invocations): void {
    $this->trackInvocation($invocations, __FUNCTION__);
  }

  /**
   * Implements hook_test_variant_hook_alter().
   */
  #[Hook(
    hook: 'test_variant_hook_alter',
    order: new OrderBefore(classesAndMethods: [[self::class, 'testHookAlter']]),
  )]
  public function testVariantHookAlter(array &$invocations): void {
    $this->trackInvocation($invocations, __FUNCTION__);
  }

}
