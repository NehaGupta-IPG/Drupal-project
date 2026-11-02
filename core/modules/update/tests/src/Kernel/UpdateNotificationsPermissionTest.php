<?php

declare(strict_types=1);

namespace Drupal\Tests\update\Kernel;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\update\Hook\UpdateRequirements;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the requirement for the "view update notifications" permission.
 */
#[CoversClass(UpdateRequirements::class)]
#[Group('update')]
#[RunTestsInSeparateProcesses]
class UpdateNotificationsPermissionTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'update',
    'user',
  ];

  /**
   * Tests the warning for roles without "view update notifications".
   */
  public function testPermissionRequirement(): void {
    // No roles have "administer site configuration".
    $this->assertArrayNotHasKey('update_notifications_permission', $this->getRequirements());

    $this->createRole(['administer site configuration'], 'site_config', 'Site config');
    $requirement = $this->getRequirements()['update_notifications_permission'];
    $this->assertSame(RequirementSeverity::Warning, $requirement['severity']);
    $description = (string) $requirement['description'];
    $this->assertStringContainsString('The <em class="placeholder">Site config</em> role has the', $description);
    $this->assertStringContainsString('/admin/people/permissions/module/update', $description);

    // A second misconfigured role is listed alongside the first.
    $this->createRole(['administer site configuration'], 'other_config', 'Other config');
    $description = (string) $this->getRequirements()['update_notifications_permission']['description'];
    $this->assertStringContainsString('The <em class="placeholder">Site config, Other config</em> roles have the', $description);

    // Granting the permission to all such roles removes the warning.
    Role::load('site_config')->grantPermission('view update notifications')->save();
    Role::load('other_config')->grantPermission('view update notifications')->save();
    $this->assertArrayNotHasKey('update_notifications_permission', $this->getRequirements());

    // Administrator roles implicitly have all permissions.
    $this->createRole([], 'admin_role', 'Admin role');
    Role::load('admin_role')->setIsAdmin(TRUE)->save();
    $this->assertArrayNotHasKey('update_notifications_permission', $this->getRequirements());
  }

  /**
   * Gets the runtime requirements of the Update module.
   *
   * @return array
   *   The runtime requirements.
   */
  protected function getRequirements(): array {
    return $this->container->get(UpdateRequirements::class)->runtime();
  }

}
