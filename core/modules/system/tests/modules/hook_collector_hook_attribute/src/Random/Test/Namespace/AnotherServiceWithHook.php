<?php

declare(strict_types=1);

namespace Drupal\hook_collector_hook_attribute\Random\Test\Namespace;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Test\Hook\TestHookService;
use Drupal\Core\Test\Hook\TrackHookInvocationsTrait;

/**
 * Another service class with Hook attribute.
 *
 * This is used to test that any service registered in the module's services.yml
 * can have hook implementations, regardless of where under the module's
 * namespace it exists.
 */
class AnotherServiceWithHook {

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
   * Implements hook_test_hook_removed().
   */
  #[Hook('test_hook_removed')]
  public function testHookRemoved(array &$invocations): void {
    $this->trackInvocation($invocations, __FUNCTION__);
  }

}
