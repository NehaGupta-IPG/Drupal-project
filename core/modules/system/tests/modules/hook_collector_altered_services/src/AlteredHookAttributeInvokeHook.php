<?php

declare(strict_types=1);

namespace Drupal\hook_collector_altered_services;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Test of service class overriding a Hook class that is not in services.yml.
 *
 * The service provider in this class alters the HookAttributeInvokeHook service
 * to use this class instead. HookAttributeInvokeHook is not registered in
 * hook_collector_hook_attribute.services.yml and is registered by the
 * HookCollectorPass, so this class is for testing that altering services
 * registered this way works.
 */
class AlteredHookAttributeInvokeHook {

  /**
   * Implements hook_cache_flush().
   */
  public function __invoke(): void {
    // With the #[Hook] attribute removed from the class, this method should not
    // run when cache_flush is invoked.
    $GLOBALS['hook_invoke_method'] = 'hook_invoke_method';
  }

  /**
   * Implements hook_test_hook_7().
   */
  #[Hook('test_hook_7')]
  public function testHook7(array &$invocations): void {
    $key = get_class($this) . '::' . __FUNCTION__;
    $invocations[$key] ??= 0;
    $invocations[$key]++;
  }

}
