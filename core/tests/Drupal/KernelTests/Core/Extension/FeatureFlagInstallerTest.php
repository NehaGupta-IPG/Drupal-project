<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Extension;

use Drupal\Core\Extension\FeatureFlagInstaller;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the FeatureFlagInstaller class.
 */
#[CoversClass(FeatureFlagInstaller::class)]
#[Group('Extension')]
#[RunTestsInSeparateProcesses]
class FeatureFlagInstallerTest extends KernelTestBase {

  /**
   * Tests that services are available after enabling a feature flag.
   */
  public function testEnableDisableFlag(): void {
    $this->assertFalse(\Drupal::getContainer()->hasParameter('test_flag_extension_is_enabled'));

    \Drupal::service(FeatureFlagInstaller::class)->enable(['test_flag']);

    $this->assertTrue(\Drupal::getContainer()->hasParameter('test_flag_extension_is_enabled'));
    $this->assertTrue(\Drupal::getContainer()->getParameter('test_flag_extension_is_enabled'));

    \Drupal::service(FeatureFlagInstaller::class)->disable(['test_flag']);

    $this->assertFalse(\Drupal::getContainer()->hasParameter('test_flag_extension_is_enabled'));
  }

}
