<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Hook;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\hook_collector_altered_services\AlteredServiceWithHook;
use Drupal\hook_collector_altered_services\DecoratedServiceWithHook;
use Drupal\hook_collector_hook_attribute\AnotherServiceWithHook;
use Drupal\hook_collector_hook_attribute\Hook\HookRegisteredService;
use Drupal\hook_collector_hook_attribute\ServiceWithHook;
use Drupal\KernelTests\KernelTestBase;

/**
 * Test hook implementations in declared services.
 *
 * @group Hook
 */
class HooksInServiceDeclarationsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Whether the hook implementation in this class ran.
   *
   * @var bool
   */
  protected bool $testHookRan = FALSE;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    // Register this class as service, so that its hook implementation gets
    // picked up.
    $container->register(__CLASS__, __CLASS__);
    $container->set(__CLASS__, $this);
    parent::register($container);
  }

  /**
   * Tests that the implementation of hooks in declared services run.
   */
  public function testHookInDeclaredService(): void {
    // Confirm that hook in this test class runs.
    $this->assertFalse($this->testHookRan);
    \Drupal::moduleHandler()->invokeAll('test_hook');
    $this->assertTrue($this->testHookRan);

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

    // Install module that decorates the ServiceWithHook service and has a
    // service provider that alters the AnotherServiceWithHook service.
    \Drupal::service('module_installer')->install(['hook_collector_altered_services']);

    // DecoratedServiceWithHook decorates ServiceWithHook without extending it.
    // When test_hook_2 is invoked, ServiceWithHook::testHook2() should not run.
    // DecoratedServiceWithHook has its own test_hook_2 implementation, and that
    // should run.
    $invocations = [];
    \Drupal::moduleHandler()->invokeAll('test_hook_2', [&$invocations]);
    $this->assertArrayNotHasKey(ServiceWithHook::class . '::testHook2', $invocations);
    $this->assertEquals(1, $invocations[DecoratedServiceWithHook::class . '::testHook2']);

    // When test_hook_5 is invoked, ServiceWithHook::testHook5() should not run.
    // Since DecorateServiceWithHook does not have a test_hook_5 implementation,
    // there should not be any method run.
    $invocations = [];
    \Drupal::moduleHandler()->invokeAll('test_hook_5', [&$invocations]);
    $this->assertEmpty($invocations);

    // AnotherServiceWithHook service class is replaced with
    // AlteredServiceWithHook by a service provider. When test_hook_3 is
    // invoked, AnotherServiceWithHook::testHook3() should not run. Since
    // AlteredServiceWithHook extends AnotherServiceWithHook and does not
    // implement its own testHook3, testHook3() should run.
    $invocations = [];
    \Drupal::moduleHandler()->invokeAll('test_hook_3', [&$invocations]);
    $this->assertArrayNotHasKey(AnotherServiceWithHook::class . '::testHook3', $invocations);
    $this->assertEquals(1, $invocations[AlteredServiceWithHook::class . '::testHook3']);

    // AlteredServiceWithHook and DecoratedServiceWithHook implement
    // test_hook_4, but their origin service classes do not. The testHook4
    // methods in AlteredServiceWithHook and DecoratedServiceWithHook should run
    // when test_hook_4 is invoked.
    $invocations = [];
    \Drupal::moduleHandler()->invokeAll('test_hook_4', [&$invocations]);
    $this->assertEquals(1, $invocations[AlteredServiceWithHook::class . '::testHook4']);
    $this->assertEquals(1, $invocations[DecoratedServiceWithHook::class . '::testHook4']);

    // AlteredServiceWithHook::testHook6() overrides its parent method in
    // AnotherServiceWithHook::testHook6(). AlteredServiceWithHook::testHook6()
    // has no Hook attribute, so when test_hook_6 is invoked, testHook6() should
    // not run.
    $invocations = [];
    \Drupal::moduleHandler()->invokeAll('test_hook_6', [&$invocations]);
    $this->assertArrayNotHasKey(AnotherServiceWithHook::class . '::testHook6', $invocations);
    $this->assertArrayNotHasKey(AlteredServiceWithHook::class . '::testHook6', $invocations);
  }

  /**
   * Implements hook_test_hook().
   *
   * This is an example of how a kernel test class can register its own methods
   * as hook implementations. Since this class is not in a module, need to set
   * the attribute's module property as "system", so that the hook
   * implementation is registered and will be invoked.
   */
  #[Hook('test_hook', module: 'system')]
  public function doTestHook(): void {
    $this->testHookRan = TRUE;
  }

}
