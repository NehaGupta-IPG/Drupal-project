<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Routing;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the URL object integration into the access system.
 */
#[Group('Url')]
#[RunTestsInSeparateProcesses]
class UrlIntegrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user', 'router_test', 'system'];

  /**
   * Ensures that the access() method on \Drupal\Core\Url objects works.
   */
  public function testAccess(): void {
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('user');

    /** @var \Drupal\user\RoleInterface $role_with_access */
    $role_with_access = Role::create(['id' => 'role_with_access', 'label' => 'With access']);
    $role_with_access->grantPermission('administer users');
    $role_with_access->save();

    /** @var \Drupal\user\RoleInterface $role_without_access */
    $role_without_access = Role::create(['id' => 'role_without_access', 'label' => 'Without access']);
    $role_without_access->save();

    $user_with_access = User::create([
      'name' => 'access',
      'roles' => ['role_with_access'],
    ]);
    $user_with_access->save();
    $user_without_access = User::create([
      'name' => 'no access',
      'roles' => ['role_without_access'],
    ]);
    $user_without_access->save();

    $user_with_access_int_id = User::create([
      'uid' => (int) $user_with_access->id(),
      'roles' => ['role_with_access'],
    ]);
    $user_without_access_int_id = User::create([
      'uid' => (int) $user_with_access->id(),
      'roles' => ['role_without_access'],
    ]);

    $anonymous_with_access = User::create(['roles' => ['role_with_access']]);
    $anonymous_without_access = User::create(['roles' => ['role_without_access']]);
    $anonymous = User::create();

    $url_always_access = new Url('router_test.1');
    $this->assertTrue($url_always_access->access($user_with_access));
    $this->assertTrue($url_always_access->access($user_without_access));
    $this->assertTrue($url_always_access->access($user_with_access_int_id));
    $this->assertTrue($url_always_access->access($user_without_access_int_id));
    $this->assertTrue($url_always_access->access($anonymous_with_access));
    $this->assertTrue($url_always_access->access($anonymous_without_access));
    $this->assertTrue($url_always_access->access($anonymous));

    $url_none_access = new Url('router_test.15');
    $this->assertFalse($url_none_access->access($user_with_access));
    $this->assertFalse($url_none_access->access($user_without_access));
    $this->assertFalse($url_none_access->access($user_with_access_int_id));
    $this->assertFalse($url_none_access->access($user_without_access_int_id));
    $this->assertFalse($url_none_access->access($anonymous_with_access));
    $this->assertFalse($url_none_access->access($anonymous_without_access));
    $this->assertFalse($url_none_access->access($anonymous));

    $url_access = new Url('router_test.16');
    $this->assertTrue($url_access->access($user_with_access));
    $this->assertFalse($url_access->access($user_without_access));
    $this->assertTrue($url_access->access($user_with_access_int_id));
    $this->assertFalse($url_access->access($user_without_access_int_id));
    $this->assertTrue($url_access->access($anonymous_with_access));
    $this->assertFalse($url_access->access($anonymous_without_access));
    $this->assertFalse($url_access->access($anonymous));
  }

}
