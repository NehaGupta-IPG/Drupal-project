<?php

namespace Drupal\Tests\user\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;

/**
 * Tests that dynamic permissions based on a config entity is correctly imported.
 *
 * @group user
 */
class UserConfigImportPermissionCircularDependencyTest extends BrowserTestBase {

  /**
   * The profile to install as a basis for testing.
   *
   * @var string
   */
  protected $profile = 'testing_config_install_circular_perm_dependency';

  /**
   * Tests that dynamic permissions based on config is correctly imported.
   */
  public function testDynamicPermissionsDuringConfigImport(): void {
    $role = Role::load('role_1');
    $this->assertTrue($role->hasPermission('role_1'));
  }

}
