<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Extension;

use Drupal\Core\Extension\ModuleHandler;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

/**
 * Test whether deprecated hook invocations trigger errors.
 */
#[CoversClass(ModuleHandler::class)]
#[Group('Extension')]
#[IgnoreDeprecations]
class ModuleHandlerDeprecatedHookTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'deprecation_test',
    'deprecation_hook_attribute_test',
  ];

  /**
   * Ensures invoke triggers deprecations for deprecated hooks.
   */
  public function testInvokeDeprecated(): void {
    $this->expectDeprecation('deprecated_hook implementations Drupal\Core\Extension\ProceduralCall::deprecation_test_deprecated_hook, Drupal\deprecation_hook_attribute_test\Hook\DeprecationHookAttributeTestHooks::deprecatedHook are deprecated in deprecation_test:x.x.x and removed in deprecation_test:y.y.y. Use something else. See change record');
    /** @var \Drupal\Core\Extension\ModuleHandlerInterface $module_handler */
    $module_handler = $this->container->get('module_handler');
    $arg = 'an_arg';
    $this->assertEquals(
      $arg,
      $module_handler->invoke('deprecation_test', 'deprecated_hook', [$arg])
    );
  }

  /**
   * Ensures invokeAll triggers deprecations for deprecated hooks.
   */
  public function testInvokeAllDeprecated(): void {
    $this->expectDeprecation('deprecated_hook implementations Drupal\Core\Extension\ProceduralCall::deprecation_test_deprecated_hook, Drupal\deprecation_hook_attribute_test\Hook\DeprecationHookAttributeTestHooks::deprecatedHook are deprecated in deprecation_test:x.x.x and removed in deprecation_test:y.y.y. Use something else. See change record');
    /** @var \Drupal\Core\Extension\ModuleHandlerInterface $module_handler */
    $module_handler = $this->container->get('module_handler');
    $arg = 'an_arg';
    $this->assertEquals(
      [
        $arg,
        $arg,
      ],
      $module_handler->invokeAll('deprecated_hook', [$arg])
    );
  }

  /**
   * Ensures alter triggers deprecations for deprecated hooks.
   */
  public function testAlterDeprecated(): void {
    $this->expectDeprecation('deprecated_hook implementations Drupal\Core\Extension\ProceduralCall::deprecation_test_deprecated_hook, Drupal\deprecation_hook_attribute_test\Hook\DeprecationHookAttributeTestHooks::deprecatedHook are deprecated in deprecation_test:x.x.x and removed in deprecation_test:y.y.y. Use something else. See change record');
    /** @var \Drupal\Core\Extension\ModuleHandlerInterface $module_handler */
    $module_handler = $this->container->get('module_handler');
    $data = [];
    $context1 = 'test1';
    $context2 = 'test2';
    $module_handler->alter('deprecated_alter', $data, $context1, $context2);
    $this->assertEquals([$context1, $context2], $data);
  }

}
