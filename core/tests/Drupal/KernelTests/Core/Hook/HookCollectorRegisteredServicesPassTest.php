<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Hook;

use Drupal\hook_collector_hook_attribute\AnotherServiceWithHook;
use Drupal\hook_collector_hook_attribute\Hook\HookRegisteredService;
use Drupal\hook_collector_hook_attribute\ServiceWithHook;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test hook implementations in declared services.
 */
#[Group('Hook')]
#[RunTestsInSeparateProcesses]
class HookCollectorRegisteredServicesPassTest extends KernelTestBase {

  /**
   * Tests that the implementation of hooks in declared services run.
   */
  public function testHookInDeclaredService(): void {
    \Drupal::service('module_installer')->install(['hook_collector_hook_attribute']);
    $invocations = [];

    // Confirm that HookRegisteredService::__invoke() only runs once.
    \Drupal::moduleHandler()->invokeAll('test_hook_1', [&$invocations]);
    $this->assertEquals(1, $invocations[HookRegisteredService::class . '::__invoke']);

    // Make sure all expected hook methods are invoked.
    $this->assertArrayNotHasKey(ServiceWithHook::class . '::testHook2', $invocations);
    \Drupal::moduleHandler()->invokeAll('test_hook_2', [&$invocations]);
    $this->assertEquals(1, $invocations[ServiceWithHook::class . '::testHook2']);
    $this->assertArrayNotHasKey(ServiceWithHook::class . '::testHook5', $invocations);
    \Drupal::moduleHandler()->invokeAll('test_hook_5', [&$invocations]);
    $this->assertEquals(1, $invocations[ServiceWithHook::class . '::testHook5']);
    $this->assertArrayNotHasKey(AnotherServiceWithHook::class . '::testHook3', $invocations);
    \Drupal::moduleHandler()->invokeAll('test_hook_3', [&$invocations]);
    $this->assertEquals(1, $invocations[AnotherServiceWithHook::class . '::testHook3']);
    $this->assertArrayNotHasKey(AnotherServiceWithHook::class . '::testHook6', $invocations);
    \Drupal::moduleHandler()->invokeAll('test_hook_6', [&$invocations]);
    $this->assertEquals(1, $invocations[AnotherServiceWithHook::class . '::testHook6']);

    $this->assertArrayNotHasKey('hook_invoke_method', $GLOBALS);
    \Drupal::moduleHandler()->invokeAll('cache_flush');
    $this->assertEquals('hook_invoke_method', $GLOBALS['hook_invoke_method']);
  }

}
