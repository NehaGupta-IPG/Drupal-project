<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;

/**
 * Performs functional tests on Drupal\user\RoleStorage.
 *
 * @group user
 */
class PermissionInRoleTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user', 'system', 'node'];

  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['user']);
    // Give anonymous users permission to access content, so they can view the
    // content.
    $anonymous_role = Role::load(AccountInterface::ANONYMOUS_ROLE);
    $anonymous_role->grantPermission('access content');
    $anonymous_role->save();
  }

  /**
   * Tests the deprecation of \Drupal\user\RoleStorage::isPermissionInRoles()
   *
   * @group legacy
   */
  public function testDeprecatedTestIsPermissionInRoles(): void {
    $isPermission = \Drupal::service('entity_type.manager')->getStorage('user_role')->isPermissionInRoles('access content', [AccountInterface::ANONYMOUS_ROLE]);
    $this->expectDeprecation('Drupal\user\RoleStorage::isPermissionInRoles() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. See https://www.drupal.org/node/3477235');
    // The $isPermission will always be true, as we assigned 'access content'
    // permission to anonymous user.
    $this->assertEquals(TRUE, $isPermission);

    $isPermission = \Drupal::service('entity_type.manager')->getStorage('user_role')->isPermissionInRoles('view own unpublished content', [AccountInterface::ANONYMOUS_ROLE]);
    $this->expectDeprecation('Drupal\user\RoleStorage::isPermissionInRoles() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. See https://www.drupal.org/node/3477235');
    // The $isPermission will always be false, as anonymous user doesn't have
    // 'view own unpublished content' permission.
    $this->assertEquals(FALSE, $isPermission);
  }

}
