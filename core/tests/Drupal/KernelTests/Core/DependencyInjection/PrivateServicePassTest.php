<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\DependencyInjection;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that event subscribers trigger deprecation on direct container access.
 */
#[Group('DependencyInjection')]
#[RunTestsInSeparateProcesses]
class PrivateServicePassTest extends KernelTestBase {

  /**
   * Tests that direct container access to event subscribers triggers deprecation.
   */
  #[IgnoreDeprecations]
  public function testDirectAccessTriggersDeprecation(): void {
    $this->expectDeprecation('Since drupal/core 11.4.0: Accessing the "main_content_view_subscriber" service directly from the container is deprecated, use dependency injection instead.');
    $this->container->get('main_content_view_subscriber');
  }

}
