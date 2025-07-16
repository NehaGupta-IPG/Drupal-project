<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Hook;

use Drupal\aaa_hook_order_test\Hook\AAlterHooks;
use Drupal\aaa_hook_order_test\Hook\ModuleImplementsAlter;
use Drupal\bbb_hook_order_test\Hook\BAlterHooks;
use Drupal\ccc_hook_order_test\Hook\CAlterHooks;
use Drupal\ddd_hook_order_test\Hook\DAlterHooks;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Hook Alter Order.
 */
#[Group('Hook')]
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
class HookAlterOrderTest extends KernelTestBase {

  use HookOrderTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'aaa_hook_order_test',
    'bbb_hook_order_test',
    'ccc_hook_order_test',
    'ddd_hook_order_test',
  ];

  /**
   * Tests procedural implementations of module implements alter ordering.
   */
  public function testProceduralModuleImplementsAlterOrder(): void {
    $this->assertAlterCallOrder($main_unaltered = [
      'aaa_hook_order_test_procedural_alter',
      'bbb_hook_order_test_procedural_alter',
      'ccc_hook_order_test_procedural_alter',
    ], 'procedural');

    $this->assertAlterCallOrder($sub_unaltered = [
      'aaa_hook_order_test_procedural_subtype_alter',
      'bbb_hook_order_test_procedural_subtype_alter',
      'ccc_hook_order_test_procedural_subtype_alter',
    ], 'procedural_subtype');

    $this->assertAlterCallOrder($combined_unaltered = [
      'aaa_hook_order_test_procedural_alter',
      'aaa_hook_order_test_procedural_subtype_alter',
      'bbb_hook_order_test_procedural_alter',
      'bbb_hook_order_test_procedural_subtype_alter',
      'ccc_hook_order_test_procedural_alter',
      'ccc_hook_order_test_procedural_subtype_alter',
    ], ['procedural', 'procedural_subtype']);

    $move_b_down = function (array &$implementations): void {
      // Move B to the end, no matter which hook.
      $group = $implementations['bbb_hook_order_test'];
      unset($implementations['bbb_hook_order_test']);
      $implementations['bbb_hook_order_test'] = $group;
    };
    $modules = ['aaa_hook_order_test', 'bbb_hook_order_test', 'ccc_hook_order_test'];

    // Test with module B moved to the end for both hooks.
    ModuleImplementsAlter::set(
      function (array &$implementations, string $hook) use ($modules, $move_b_down): void {
        if (!in_array($hook, ['procedural_alter', 'procedural_subtype_alter'])) {
          return;
        }
        $this->assertSame($modules, array_keys($implementations));
        $move_b_down($implementations);
      },
    );
    \Drupal::service('kernel')->rebuildContainer();

    $this->assertAlterCallOrder($main_altered = [
      'aaa_hook_order_test_procedural_alter',
      'ccc_hook_order_test_procedural_alter',
      // The implementation of B has been moved.
      'bbb_hook_order_test_procedural_alter',
    ], 'procedural');

    $this->assertAlterCallOrder($sub_altered = [
      'aaa_hook_order_test_procedural_subtype_alter',
      'ccc_hook_order_test_procedural_subtype_alter',
      // The implementation of B has been moved.
      'bbb_hook_order_test_procedural_subtype_alter',
    ], 'procedural_subtype');

    $this->assertAlterCallOrder($combined_altered = [
      'aaa_hook_order_test_procedural_alter',
      'aaa_hook_order_test_procedural_subtype_alter',
      'ccc_hook_order_test_procedural_alter',
      'ccc_hook_order_test_procedural_subtype_alter',
      // The implementation of B has been moved.
      'bbb_hook_order_test_procedural_alter',
      'bbb_hook_order_test_procedural_subtype_alter',
    ], ['procedural', 'procedural_subtype']);

    // If the altered hook is not the first one, implementations are back in
    // their unaltered order.
    $this->assertAlterCallOrder($main_unaltered, ['other_main_type', 'procedural']);
    $this->assertAlterCallOrder($sub_unaltered, ['other_main_type', 'procedural_subtype']);
    $this->assertAlterCallOrder($combined_unaltered, ['other_main_type', 'procedural', 'procedural_subtype']);

    // Test with module B moved to the end for the main hook.
    ModuleImplementsAlter::set(
      function (array &$implementations, string $hook) use ($modules, $move_b_down): void {
        if (!in_array($hook, ['procedural_alter', 'procedural_subtype_alter'])) {
          return;
        }
        $this->assertSame($modules, array_keys($implementations));
        if ($hook !== 'procedural_alter') {
          return;
        }
        $move_b_down($implementations);
      },
    );
    \Drupal::service('kernel')->rebuildContainer();

    $this->assertAlterCallOrder($main_altered, 'procedural');
    $this->assertAlterCallOrder($sub_unaltered, 'procedural_subtype');
    $this->assertAlterCallOrder($combined_altered, ['procedural', 'procedural_subtype']);

    // Test with module B moved to the end for the subtype hook.
    ModuleImplementsAlter::set(
      function (array &$implementations, string $hook) use ($modules, $move_b_down): void {
        if (!in_array($hook, ['procedural_alter', 'procedural_subtype_alter'])) {
          return;
        }
        $this->assertSameCallList($modules, array_keys($implementations));
        if ($hook !== 'procedural_subtype_alter') {
          return;
        }
        $move_b_down($implementations);
      },
    );
    \Drupal::service('kernel')->rebuildContainer();

    $this->assertAlterCallOrder($main_unaltered, 'procedural');
    $this->assertAlterCallOrder($sub_altered, 'procedural_subtype');
    $this->assertAlterCallOrder($combined_unaltered, ['procedural', 'procedural_subtype']);
  }

  /**
   * Test ordering alter calls.
   */
  public function testAlterOrder(): void {
    $this->doTestAlterOrder();
  }

  /**
   * Test alter calls where some types have no implementations.
   */
  public function testAlterWithNonExistingType(): void {
    $this->assertAlterCallOrder([], 'non_implemented_type');
    $this->assertAlterCallOrder([], ['non_implemented_type', 'other_non_implemented_type']);

    // Prepending or appending a non-implemented type makes no difference.
    $expected = $this->alter('test');
    $this->assertNotEmpty($expected);
    // Simulate a "base type" with no implementations.
    $this->assertAlterCallOrder($expected, ['non_implemented_type', 'test']);
    // Simulate a "subtype" with no implementations.
    $this->assertAlterCallOrder($expected, ['test', 'non_implemented_type']);
    // Simulate a "base type" and a "sub-sub type" with no implementations.
    $this->assertAlterCallOrder($expected, ['non_implemented_type', 'test', 'other_non_implemented_type']);

    // Run the existing test case with added non-implemented types.
    // This ensures a broad coverage.
    $assert_alter_call_order = function (array $expected, array|string $type): void {
      $extended_types = ['non_implemented_type', ...(array) $type, 'other_non_implemented_type'];
      $this->assertAlterCallOrder($expected, $extended_types);
    };

    $this->doTestAlterOrder($assert_alter_call_order);
  }

  /**
   * Test alter calls where some types are repeated.
   */
  public function testAlterWithNonUniqueTypes(): void {
    // Repeating a type with no implementations results in an empty list.
    $this->assertAlterCallOrder([], ['non_implemented_type', 'non_implemented_type']);

    // Repeating a single type results in a specific notice.
    $notices = $this->assertAlterCallOrderAndCaptureErrors($expected = [
      CAlterHooks::class . '::testAlter',
      AAlterHooks::class . '::testAlterAfterC',
      DAlterHooks::class . '::testAlter',
    ], $type = ['test', 'test']);
    $this->assertSame([
      'The method Drupal\ccc_hook_order_test\Hook\CAlterHooks::testAlter() is registered for more than one of the alter hooks [&#039;test_alter&#039;, &#039;test_alter&#039;] from the current ->alter() call. Only one instance will be part of the implementation list for this hook combination.',
      'The method Drupal\aaa_hook_order_test\Hook\AAlterHooks::testAlterAfterC() is registered for more than one of the alter hooks [&#039;test_alter&#039;, &#039;test_alter&#039;] from the current ->alter() call. Only one instance will be part of the implementation list for this hook combination.',
      'The method Drupal\ddd_hook_order_test\Hook\DAlterHooks::testAlter() is registered for more than one of the alter hooks [&#039;test_alter&#039;, &#039;test_alter&#039;] from the current ->alter() call. Only one instance will be part of the implementation list for this hook combination.',
    ], $notices);

    // Repeated calls do not produce more notices, due to caching.
    $notices = $this->assertAlterCallOrderAndCaptureErrors($expected, $type);
    $this->assertSame([], $notices);

    // Run the existing test with a callback that inserts repeated types.
    // This verifies that appending repeated types has no effect.
    $assert_alter_call_order = function (array $expected, array|string $original_type): void {
      $unique_types = (array) $original_type;

      $non_unique_types = [];
      foreach ((array) $original_type as $type) {
        $non_unique_types[] = $type;
        $non_unique_types[] = $type;
      }
      $this->assertAlterCallOrder($expected, $non_unique_types);

      $non_unique_types = [...$unique_types, ...$unique_types];
      $this->assertAlterCallOrder($expected, $non_unique_types);

      $non_unique_types = [...$unique_types, $unique_types[0]];
      $this->assertAlterCallOrder($expected, $non_unique_types);
    };

    $notices = $this->callAndCaptureErrors(
      fn () => $this->doTestAlterOrder($assert_alter_call_order),
      E_USER_NOTICE,
    );

    // At least some invocations should produce notices.
    $this->assertNotEmpty($notices);
  }

  /**
   * Tests the order of alter calls.
   *
   * This is called from multiple test methods.
   *
   * @param (\Closure(list<string>, string|list<string>): void)|null $assert_alter_call_order
   *   Custom replacement for ::assertAlterCallOrder().
   */
  protected function doTestAlterOrder(?\Closure $assert_alter_call_order = NULL): void {
    $assert_alter_call_order ??= $this->assertAlterCallOrder(...);

    $assert_alter_call_order([
      CAlterHooks::class . '::testAlter',
      AAlterHooks::class . '::testAlterAfterC',
      DAlterHooks::class . '::testAlter',
    ], 'test');

    $assert_alter_call_order([
      AAlterHooks::class . '::testSubtypeAlter',
      BAlterHooks::class . '::testSubtypeAlter',
      CAlterHooks::class . '::testSubtypeAlter',
      DAlterHooks::class . '::testSubtypeAlter',
    ], 'test_subtype');

    $assert_alter_call_order([
      // The implementation from 'D' is gone.
      AAlterHooks::class . '::testSubtypeAlter',
      BAlterHooks::class . '::testSubtypeAlter',
      CAlterHooks::class . '::testAlter',
      CAlterHooks::class . '::testSubtypeAlter',
      AAlterHooks::class . '::testAlterAfterC',
      DAlterHooks::class . '::testAlter',
      DAlterHooks::class . '::testSubtypeAlter',
    ], ['test', 'test_subtype']);

    $this->disableModules(['bbb_hook_order_test']);

    $assert_alter_call_order([
      CAlterHooks::class . '::testAlter',
      AAlterHooks::class . '::testAlterAfterC',
      DAlterHooks::class . '::testAlter',
    ], 'test');

    $assert_alter_call_order([
      AAlterHooks::class . '::testSubtypeAlter',
      CAlterHooks::class . '::testSubtypeAlter',
      DAlterHooks::class . '::testSubtypeAlter',
    ], 'test_subtype');

    $assert_alter_call_order([
      AAlterHooks::class . '::testSubtypeAlter',
      CAlterHooks::class . '::testAlter',
      CAlterHooks::class . '::testSubtypeAlter',
      AAlterHooks::class . '::testAlterAfterC',
      DAlterHooks::class . '::testAlter',
      DAlterHooks::class . '::testSubtypeAlter',
    ], ['test', 'test_subtype']);
  }

  /**
   * Invokes a callback and captures errors of a specific level.
   *
   * @param \Closure(): void $callback
   *   Callback that may or may not produce notices.
   * @param int $captured_level
   *   The error level for captured errors.
   *   Errors with a different type are not captured.
   *
   * @return list<string>
   *   Captured error messages.
   */
  protected function callAndCaptureErrors(\Closure $callback, int $captured_level): array {
    $messages = [];
    $previous_error_handler = get_error_handler();
    $capturing_error_handler = function (int $level, string $message, ...$args) use (&$messages, $previous_error_handler, $captured_level): bool {
      if ($level !== $captured_level) {
        return $previous_error_handler($level, $message, ...$args);
      }
      $messages[] = $message;
      return TRUE;
    };
    $previous_error_handler = set_error_handler($capturing_error_handler);
    try {
      $callback();
    }
    finally {
      restore_error_handler();
    }
    return $messages;
  }

  /**
   * Asserts the call order from an alter call, and collects errors.
   *
   * @param list<string> $expected
   *   Expected call list, as strings from __METHOD__ or __FUNCTION__.
   * @param string|list<string> $type
   *   First argument to pass to ->alter().
   * @param int $level
   *   Error level for which to capture messages.
   */
  protected function assertAlterCallOrderAndCaptureErrors(array $expected, string|array $type, int $level = E_USER_NOTICE): array {
    return $this->callAndCaptureErrors(
      fn () => $this->assertAlterCallOrder($expected, $type),
      $level,
    );
  }

  /**
   * Asserts the call order from an alter call.
   *
   * Also asserts additional $type argument values that are meant to produce the
   * same result.
   *
   * @param list<string> $expected
   *   Expected call list, as strings from __METHOD__ or __FUNCTION__.
   * @param string|list<string> $type
   *   First argument to pass to ->alter().
   */
  protected function assertAlterCallOrder(array $expected, string|array $type): void {
    $this->assertSameCallList(
      $expected,
      $this->alter($type),
    );
  }

  /**
   * Invokes ModuleHandler->alter() and returns the altered array.
   *
   * @param string|list<string> $type
   *   Alter type or list of alter types.
   *
   * @return array
   *   The altered array.
   */
  protected function alter(string|array $type): array {
    $data = [];
    \Drupal::moduleHandler()->alter($type, $data);
    return $data;
  }

}
