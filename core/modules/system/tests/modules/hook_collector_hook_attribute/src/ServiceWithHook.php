<?php

declare(strict_types=1);

namespace Drupal\hook_collector_hook_attribute;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Test\TestHookService;
use Drupal\Core\Test\TrackHookInvocationsTrait;

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
   * Implements hook_test_hook_2().
   */
  #[Hook('test_hook_2')]
  public function testHook2(array &$invocations): void {
    $this->trackInvocation($invocations, __FUNCTION__);
  }

  /**
   * Implements hook_test_hook_5().
   */
  #[Hook('test_hook_5')]
  public function testHook5(array &$invocations): void {
    $this->trackInvocation($invocations, __FUNCTION__);
  }

}
