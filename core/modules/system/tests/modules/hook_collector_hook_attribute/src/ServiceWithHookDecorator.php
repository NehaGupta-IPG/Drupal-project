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
   * Implements hook_test_hook_in_decorator().
   */
  #[Hook('test_hook_decorator')]
  public function testHookInDecorator(array &$invocations): void {
    // This is an implementation of a hook method in a service that decorates
    // another class. Even though this hook is not implemented in the original
    // service class, services that decorate other services are ignored in all
    // hook discovery.
    $this->trackInvocation($invocations, __FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  #[Hook('test_hook_decorated')]
  public function testHookDecorated(array &$invocations): void {
    // This is an implementation of a hook method on the decorated class that
    // should not be invoked, because it is in a service decorator.
    $this->inner->testHookDecorated($invocations);

    $this->trackInvocation($invocations, __FUNCTION__);
  }

}
