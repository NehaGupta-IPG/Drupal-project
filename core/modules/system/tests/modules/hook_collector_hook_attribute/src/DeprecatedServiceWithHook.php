<?php

declare(strict_types=1);

namespace Drupal\hook_collector_hook_attribute;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Test\Hook\TrackHookInvocationsTrait;

// phpcs:ignore Drupal.Semantics.FunctionTriggerError
@trigger_error(__NAMESPACE__ . '\DeprecatedServiceWithHook is deprecated.', E_USER_DEPRECATED);

/**
 * Fixture for HookCollectorRegisteredServicesPassTest.
 *
 * This class is arbitrarily deprecated in order to test container service
 * deprecations.
 */
class DeprecatedServiceWithHook {

  use TrackHookInvocationsTrait;

  /**
   * Implements test_hook_1().
   */
  #[Hook('test_hook_1')]
  public function testHook1(array &$invocations): void {
    // This hook implementation should not be discovered or invoked,because it
    // is in a deprecated service.
    $this->trackInvocation($invocations, __FUNCTION__);
  }

}
