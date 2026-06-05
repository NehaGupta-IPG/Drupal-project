<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Site;

use Drupal\Core\Site\MaintenanceModeResolver;
use Drupal\Core\Site\MaintenanceType;
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

  /**
   * Tests that maintenance types are correctly resolved.
   */
  public function testMaintenanceTypes(): void {
    $resolver = new MaintenanceModeResolver();

    // Default is TRUE (bool), which resolves to Regular enum.
    $resolver->enableMaintenance();
    $this->assertTrue($resolver->isMaintenance());
    $this->assertSame(MaintenanceType::Regular, $resolver->getMaintenanceType());
    $this->assertTrue($resolver->is(MaintenanceType::Regular));
    $this->assertTrue($resolver->is('regular'));

    // Enum type Install.
    $resolver->enableMaintenance(MaintenanceType::Install);
    $this->assertTrue($resolver->isMaintenance());
    $this->assertSame(MaintenanceType::Install, $resolver->getMaintenanceType());
    $this->assertTrue($resolver->is(MaintenanceType::Install));
    $this->assertTrue($resolver->is('install'));

    // String type 'install'.
    $resolver->enableMaintenance('install');
    $this->assertTrue($resolver->isMaintenance());
    // @phpstan-ignore argument.unresolvableType
    $this->assertSame(MaintenanceType::Install, $resolver->getMaintenanceType());
    $this->assertTrue($resolver->is(MaintenanceType::Install));

    // String type 'update'.
    $resolver->enableMaintenance('update');
    $this->assertTrue($resolver->isMaintenance());
    $this->assertSame(MaintenanceType::Update, $resolver->getMaintenanceType());
    $this->assertTrue($resolver->is(MaintenanceType::Update));

    // Custom maintenance type.
    $resolver->enableMaintenance('custom_type');
    $this->assertTrue($resolver->isMaintenance());
    $this->assertSame('custom_type', $resolver->getMaintenanceType());
    $this->assertTrue($resolver->is('custom_type'));
    $this->assertFalse($resolver->is(MaintenanceType::Regular));

    $resolver->disableMaintenance();
    $this->assertNull($resolver->getMaintenanceType());
    $this->assertFalse($resolver->is(MaintenanceType::Regular));
  }

}
