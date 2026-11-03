<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Module;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests module file extension deprecations.
 */
#[Group('Module')]
#[RunTestsInSeparateProcesses]
class ModuleDeprecationTest extends KernelTestBase {

  /**
   * Tests that .module deprecations are emitted.
   */
  #[IgnoreDeprecations]
  public function testModuleExtensionDeprecation(): void {
    \Drupal::service('module_installer')->install(['test_module_discovery_deprecation']);
    $this->expectUserDeprecationMessage('Code in test_module_discovery_deprecation.module is deprecated in drupal:11.5.0 and must be converted before drupal:13.0.0. Use classes instead. See https://www.drupal.org/node/3619765');
  }

}
