<?php

declare(strict_types=1);

namespace Drupal\hook_collector_hook_attribute;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Test\Hook\TrackHookInvocationsTrait;

/**
 * Test service decorator class with hooks.
 *
 * None of the hook implementations in this class will be registered or invoked.
 */
class ServiceWithHookDecorator extends ServiceWithHook {

  use TrackHookInvocationsTrait;

  public function __construct(protected readonly ServiceWithHook $inner) {}

  /**
   * Implements hook_test_hook_1().
   */
  #[Hook('test_hook_1')]
  public function testHook1(array &$invocations): void {
    // This method should not be invoked, because it is in a service decorator.
    $this->trackInvocation($invocations, __FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  #[Hook('test_hook_2')]
  public function testHook2(array &$invocations): void {
    // This is an implementation of a hook method on the decorated class that
    // should not be invoked, because it is in a service decorator.
    $this->inner->testHook2($invocations);

    $this->trackInvocation($invocations, __FUNCTION__);
  }

}
