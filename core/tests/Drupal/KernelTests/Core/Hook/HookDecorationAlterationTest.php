<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Hook;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\hook_collector_alter_decorate_target\Hook\TestHooks;
use Drupal\hook_collector_alter_hook_class\TestHooksAltered;
use Drupal\hook_collector_decorate_hook_class\TestHooksDecorated;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Demonstrates how altered and decorated services interact with OOP Hooks.
 */
#[Group('Hook')]
#[RunTestsInSeparateProcesses]
class HookDecorationAlterationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'hook_collector_alter_decorate_target',
    'hook_collector_alter_decorate_order_remove',
  ];

  /**
   * Test altering or decorating hook classes.
   */
  #[TestWith(['hook_collector_decorate_hook_class', TestHooksDecorated::class])]
  #[TestWith(['hook_collector_alter_hook_class', TestHooksAltered::class])]
  public function testHookDecorationOrAlteration(string $module, string $replacementClass): void {
    \Drupal::service(ModuleInstallerInterface::class)->install([$module]);
    $this->assertInstanceOf($replacementClass, \Drupal::service(TestHooks::class));
    $moduleHandler = \Drupal::moduleHandler();
    // Confirm only the hook method in the replacement class matching the
    // original hook method name runs.
    $variables = [];
    $moduleHandler->invokeAll('test_method_replaced', [&$variables]);
    $this->assertSame([$replacementClass . '::testMethodReplaced' => TRUE], $variables);

    // Confirm that if the replacement class does not have the hook method,
    // there is an error when the hook is invoked.
    $variables = [];
    try {
      $moduleHandler->invokeAll('test_method_replacement_missing', [&$variables]);
      $e = NULL;
    }
    catch (\InvalidArgumentException $e) {
    }
    $this->assertInstanceOf(\InvalidArgumentException::class, $e);
    $this->assertSame('The callable definition provided was invalid. Either class "' . $replacementClass . '" does not have a method "testMethodReplacementMissing", or it is not callable.', $e->getMessage());
    $this->assertSame([], $variables);

    $variables = [];
    $moduleHandler->invokeAll('test_method_order', [&$variables]);
    $this->assertSame([
      'Drupal\hook_collector_alter_decorate_order_remove\Hook\TestOrderReorderRemoveHooks::testMethodOrderBefore' => TRUE,
      $replacementClass . '::testMethodOrder' => TRUE,
      'Drupal\hook_collector_alter_decorate_order_remove\Hook\TestOrderReorderRemoveHooks::testMethodOrderAfter' => TRUE,
    ], $variables);

    $variables = [];
    $moduleHandler->invokeAll('test_method_reorder', [&$variables]);
    $this->assertSame([
      $replacementClass . '::testMethodReorder' => TRUE,
      'Drupal\hook_collector_alter_decorate_order_remove\Hook\TestOrderReorderRemoveHooks::reorderHook' => TRUE,
    ], $variables);

    $variables = [];
    $moduleHandler->invokeAll('test_method_remove', [&$variables]);
    $this->assertSame([], $variables);
  }

}
