<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Site;

use Drupal\Core\Site\MaintenanceModeResolver;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the MaintenanceModeResolver.
 */
#[Group('Site')]
#[RunTestsInSeparateProcesses]
class MaintenanceModeResolverTest extends UnitTestCase {
  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Reset the static override between tests.
    (new MaintenanceModeResolver())->disableMaintenance();
    parent::tearDown();
  }

  /**
   * Tests isMaintenance() returns FALSE when not in maintenance mode.
   */
  public function testNotInMaintenance(): void {
    $resolver = new MaintenanceModeResolver();

    // Without MAINTENANCE_MODE constant and without a container,
    // the resolver should return FALSE.
    $this->assertFalse($resolver->isMaintenance());
  }

  /**
   * Tests enableMaintenance() activates maintenance mode.
   */
  public function testEnableMaintenance(): void {
    $resolver = new MaintenanceModeResolver();

    $result = $resolver->enableMaintenance();
    $this->assertTrue($resolver->isMaintenance());
    // Verify fluent interface.
    $this->assertSame($resolver, $result);
  }

  /**
   * Tests disableMaintenance() clears the programmatic override.
   */
  public function testDisableMaintenance(): void {
    $resolver = new MaintenanceModeResolver();

    $resolver->enableMaintenance();
    $this->assertTrue($resolver->isMaintenance());

    $result = $resolver->disableMaintenance();
    // Without constant or container, should return FALSE.
    $this->assertFalse($resolver->isMaintenance());
    // Verify fluent interface.
    $this->assertSame($resolver, $result);
  }

  /**
   * Tests that the override persists across different instances.
   */
  public function testOverridePersistsAcrossInstances(): void {
    $resolver1 = new MaintenanceModeResolver();
    $resolver1->enableMaintenance();

    // A new instance should see the override set by the first instance.
    $resolver2 = new MaintenanceModeResolver();
    $this->assertTrue($resolver2->isMaintenance());

    // Disabling via the second instance clears it for all.
    $resolver2->disableMaintenance();
    $this->assertFalse($resolver1->isMaintenance());
  }

}
