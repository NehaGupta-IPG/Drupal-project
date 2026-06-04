<?php

declare(strict_types=1);

namespace Drupal\hook_collector_altered_services;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Test\Hook\TrackHookInvocationsTrait;
use Drupal\hook_collector_hook_attribute\Hook\HookRegisteredService;

/**
 * Class that replaces AnotherServiceWithHook in service definition.
 *
 * Altering Hook-namespaced services is not supported, so installing the
 * hook_collector_altered_services module should trigger a deprecation.
 */
class AlteredHookRegisteredService extends HookRegisteredService {

  use TrackHookInvocationsTrait;

  /**
   * Implements hook_test_hook_1().
   */
  #[Hook('test_hook_1')]
  public function testHook1(array &$invocations): void {
    $this->trackInvocation($invocations, __FUNCTION__);
  }

}
