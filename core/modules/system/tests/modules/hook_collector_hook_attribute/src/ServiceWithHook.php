<?php

declare(strict_types=1);

namespace Drupal\hook_collector_hook_attribute;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Attribute\RemoveHook;
use Drupal\Core\Hook\Attribute\ReorderHook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Test\Hook\TestHookService;
use Drupal\Core\Test\Hook\TrackHookInvocationsTrait;
use Drupal\hook_collector_hook_attribute\Hook\HookRegisteredService;
use Drupal\hook_collector_hook_attribute\Random\Test\Namespace\AnotherServiceWithHook;

/**
 * Service class with Hook attribute.
 *
 * This is used to test that any service registered in the module's services.yml
 * can have hook implementations, regardless of where under the module's
 * namespace it exists.
 */
class ServiceWithHook {

  use TrackHookInvocationsTrait;

  /**
   * Implements hook_test_hook_1().
   */
  #[Hook(
    hook: 'test_hook_1',
    order: new OrderAfter(classesAndMethods: [[TestHookService::class, 'testHook1']])),
  ]
  public function testHook1(array &$invocations): void {
    $this->trackInvocation($invocations, __FUNCTION__);
  }

  /**
   * Implements hook_test_hook_decorated().
   */
  #[Hook('test_hook_decorated')]
  public function testHookDecorated(array &$invocations): void {
    $this->trackInvocation($invocations, __FUNCTION__);
  }

  #[ReorderHook(
    hook: 'test_hook_alter',
    class: HookRegisteredService::class,
    method: 'testHookAlter',
    order: new OrderAfter(['core']),
  )]
  public function testReorderHook(): void {}

  /**
   * Removes a test_hook_removed implementation.
   */
  #[RemoveHook('test_hook_removed', AnotherServiceWithHook::class, 'testHookRemoved')]
  public function testHookRemoved(): void {}

}
