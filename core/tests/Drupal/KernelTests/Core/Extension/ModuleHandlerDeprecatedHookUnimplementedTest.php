<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Extension;

use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test whether unimplemented deprecated hook invocations trigger errors.
 */
#[CoversClass(ModuleHandler::class)]
#[Group('Extension')]
class ModuleHandlerDeprecatedHookUnimplementedTest extends KernelTestBase {

  /**
   * Ensures deprecations are not triggered if no implementations are detected.
   */
  public function testUnimplementedHooks(): void {
    $unimplemented_hook_name = 'unimplemented_hook_name';

    /** @var \Drupal\Core\Extension\ModuleHandlerInterface $module_handler */
    $module_handler = $this->container->get('module_handler');
    $this->assertInstanceOf(ModuleHandlerInterface::class, $module_handler);

    $module_handler->invoke('deprecation_test', $unimplemented_hook_name);
    $module_handler->invokeAll($unimplemented_hook_name);
    $data = [];
    $module_handler->alter($unimplemented_hook_name, $data);
  }

}
