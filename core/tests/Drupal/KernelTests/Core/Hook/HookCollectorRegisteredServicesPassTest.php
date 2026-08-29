<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Hook;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Test\Hook\TestHookService;
use Drupal\hook_collector_hook_attribute\DeprecatedServiceWithHook;
use Drupal\hook_collector_hook_attribute\Event\TestEvent;
use Drupal\hook_collector_hook_attribute\EventSubscriber\EventSubscriberWithHook;
use Drupal\hook_collector_hook_attribute\Hook\HookRegisteredService;
use Drupal\hook_collector_hook_attribute\Random\Test\Namespace\AnotherServiceWithHook;
use Drupal\hook_collector_hook_attribute\ServiceWithHook;
use Drupal\hook_collector_hook_attribute\ServiceWithHookDecorator;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Test hook implementations in declared services.
 */
#[Group('Hook')]
#[RunTestsInSeparateProcesses]
class HookCollectorRegisteredServicesPassTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['hook_collector_hook_attribute'];

  /**
   * Tests that the implementation of hooks in declared services run.
   */
  #[IgnoreDeprecations]
  public function testHookInDeclaredService(): void {
    $invocations = [];

    // Test that implementations of test_hook_1 are invoked the right number of
    // times and in the right order.
    \Drupal::moduleHandler()->invokeAll('test_hook_1', [&$invocations]);
    $this->assertSame([
      TestHookService::class . '::testHook1' => 1,
      HookRegisteredService::class . '::__invoke' => 1,
      EventSubscriberWithHook::class . '::testHook1' => 1,
      AnotherServiceWithHook::class . '::testHook1' => 1,
      ServiceWithHook::class . '::testHook1' => 1,
    ], $invocations);
    // Confirm that the deprecated service has a test_hook_1 implementation.
    // Since the implementation is in a deprecated class, it was not invoked.
    $this->expectUserDeprecationMessage('The "Drupal\hook_collector_hook_attribute\DeprecatedServiceWithHook" service is deprecated.');
    $deprecatedService = \Drupal::service(DeprecatedServiceWithHook::class);
    $this->assertTrue(method_exists($deprecatedService, 'testHook1'));
    $reflectionMethod = new \ReflectionMethod($deprecatedService, 'testHook1');
    $attributes = $reflectionMethod->getAttributes(Hook::class);
    $this->assertNotEmpty($attributes);
    $this->assertSame('test_hook_1', $attributes[0]->newInstance()->hook);

    // Invoke alter hooks, with multiple types of related hooks and confirm that
    // both hookTestHookAlter() and hookTestVariantHookAlter() executed.
    $alterHooks = ['test_hook', 'test_variant_hook'];
    $invocations = [];
    \Drupal::moduleHandler()->alter($alterHooks, $invocations);
    $this->assertSame([
      TestHookService::class . '::testVariantHookAlter' => 1,
      TestHookService::class . '::testHookAlter' => 1,
      HookRegisteredService::class . '::testHookAlter' => 1,
    ], $invocations);

    // Confirm that the RemoveHook in ServiceWith Hook prevents the
    // 'test_hook_removed' in AnotherServiceWithHook from being invoked.
    $invocations = [];
    \Drupal::moduleHandler()->invokeAll('test_hook_removed', [&$invocations]);
    $this->assertEmpty($invocations);
    // Confirm that AnotherServiceWithHook has a test_hook_removed implementation.
    $deprecatedService = \Drupal::service(AnotherServiceWithHook::class);
    $this->assertTrue(method_exists($deprecatedService, 'testHookRemoved'));
    $reflectionMethod = new \ReflectionMethod($deprecatedService, 'testHookRemoved');
    $attributes = $reflectionMethod->getAttributes(Hook::class);
    $this->assertNotEmpty($attributes);
    $this->assertSame('test_hook_removed', $attributes[0]->newInstance()->hook);

    // The ServiceWithHook test_hook_decorated implementation should be the only
    // method invoked, because the methods in ServiceWithHookDecorator,
    // which decorates ServiceWithHook, will not be registered.
    $invocations = [];
    $decoratedService = \Drupal::getContainer()->get(ServiceWithHook::class);
    $this->assertInstanceOf(ServiceWithHookDecorator::class, $decoratedService);
    $this->assertTrue(method_exists($decoratedService, 'testHookDecorated'));
    \Drupal::moduleHandler()->invokeAll('test_hook_decorated', [&$invocations]);
    $this->assertSame([ServiceWithHook::class . '::testHookDecorated' => 1], $invocations);

    // Any hook implementations in service decorators are ignored.
    $invocations = [];
    $this->assertTrue(method_exists($decoratedService, 'testHookInDecorator'));
    \Drupal::moduleHandler()
      ->invokeAll('test_hook_in_decorator', [&$invocations]);
    $this->assertEmpty($invocations);

    // Confirm that an event subscriber service that contains a hook
    // implementation will not have its event handlers fired more than once,
    // even though HookCollectorPass will register another service for the same
    // class.
    $invocations = [];
    $event = new TestEvent($invocations);
    \Drupal::service(EventDispatcherInterface::class)->dispatch($event);
    $invocations = $event->getInvocations();
    $this->assertSame([EventSubscriberWithHook::class . '::onEvent' => 1], $invocations);
    $this->assertSame(get_class(\Drupal::service(EventSubscriberWithHook::class)), get_class(\Drupal::service('hook.' . EventSubscriberWithHook::class)));
  }

  /**
   * Tests that replacing hook services is deprecated or throws an exception.
   */
  #[IgnoreDeprecations]
  public function testHookServiceDecorationAlterationPrevention(): void {
    $this->expectUserDeprecationMessage('Altering hook service Drupal\\hook_collector_hook_attribute\\Hook\\HookRegisteredService is deprecated in drupal:11.5.0 and is not supported in drupal:12.0.0. Use the ReorderHook or RemoveHook attributes to control hook methods instead. See https://www.drupal.org/node/3526437');
    $this->expectExceptionMessage('Service(s) "Drupal\hook_collector_altered_services\AnotherServiceWithHookDecorator" can not decorate a hook service');
    $this->expectException(\LogicException::class);
    \Drupal::service(ModuleInstallerInterface::class)->install(['hook_collector_altered_services']);
  }

}
