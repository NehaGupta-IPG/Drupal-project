<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Hook;

use Drupal\Core\Test\TestHookService;
use Drupal\hook_collector_hook_attribute\AnotherServiceWithHook;
use Drupal\hook_collector_hook_attribute\Hook\HookRegisteredService;
use Drupal\hook_collector_hook_attribute\ServiceWithHook;
use Drupal\hook_collector_hook_attribute\ServiceWithHookDecorator;
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
    $this->assertSame([
      TestHookService::class . '::testHook1' => 1,
      HookRegisteredService::class . '::__invoke' => 1,
      ServiceWithHook::class . '::testHook1' => 1,
    ], $invocations);

    // Invoke alter hooks, with multiple types of related hooks and confirm that
    // both hookTestHookAlter() and hookTestVariantHookAlter() executed.
    $alterHooks = ['test_hook', 'test_variant_hook'];
    $invocations = [];
    \Drupal::moduleHandler()->alter($alterHooks, $invocations);
    $this->assertSame([
      HookRegisteredService::class . '::testHookAlter' => 1,
      TestHookService::class . '::testVariantHookAlter' => 1,
      TestHookService::class . '::testHookAlter' => 1,
    ], $invocations);

    // The test_hook_2 implementation should be the only method invoked, because
    // the methods in ServiceWithHookDecorator which decorates ServiceWithHook
    // will not be registered.
    $invocations = [];
    $this->assertInstanceOf(ServiceWithHookDecorator::class, \Drupal::getContainer()->get(ServiceWithHook::class));
    \Drupal::moduleHandler()->invokeAll('test_hook_2', [&$invocations]);
    $this->assertSame([ServiceWithHook::class . '::testHook2' => 1], $invocations);

    $this->assertArrayNotHasKey(ServiceWithHook::class . '::testHook5', $invocations);
    \Drupal::moduleHandler()->invokeAll('test_hook_5', [&$invocations]);
    $this->assertEquals(1, $invocations[ServiceWithHook::class . '::testHook5']);
    $this->assertArrayNotHasKey(AnotherServiceWithHook::class . '::testHook3', $invocations);
    \Drupal::moduleHandler()->invokeAll('test_hook_3', [&$invocations]);
    $this->assertEquals(1, $invocations[AnotherServiceWithHook::class . '::testHook3']);
    $this->assertArrayNotHasKey(AnotherServiceWithHook::class . '::testHook6', $invocations);
    \Drupal::moduleHandler()->invokeAll('test_hook_6', [&$invocations]);
    $this->assertEquals(1, $invocations[AnotherServiceWithHook::class . '::testHook6']);
  }

}
