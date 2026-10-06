<?php

declare(strict_types=1);

namespace Drupal\Tests\update\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the status report warning for the update notifications permission.
 */
#[Group('update')]
#[RunTestsInSeparateProcesses]
class UpdateNotificationsPermissionTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['update'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the warning for roles without "view update notifications".
   */
  public function testPermissionWarning(): void {
    $assert_session = $this->assertSession();
    $role_id = $this->drupalCreateRole(['administer site configuration'], 'site_config', 'Site config');
    $this->drupalLogin($this->drupalCreateUser([], NULL, FALSE, ['roles' => [$role_id]]));

    $this->drupalGet('admin/reports/status');
    $assert_session->pageTextContains('Update notifications permission');
    $assert_session->pageTextContains('The Site config role has the Administer site configuration permission but not the View software update notifications permission');
    $assert_session->linkByHrefExists('/admin/people/permissions/module/update');

    // A second misconfigured role is listed alongside the first.
    $this->drupalCreateRole(['administer site configuration'], 'other_config', 'Other config');
    $this->drupalGet('admin/reports/status');
    $assert_session->pageTextContains('The Site config, Other config roles have the Administer site configuration permission');

    // Granting the permission to all such roles removes the warning.
    Role::load('site_config')->grantPermission('view update notifications')->save();
    Role::load('other_config')->grantPermission('view update notifications')->save();
    $this->drupalGet('admin/reports/status');
    $assert_session->pageTextNotContains('Update notifications permission');

    // Administrator roles implicitly have all permissions.
    $this->drupalCreateRole([], 'admin_role', 'Admin role');
    Role::load('admin_role')->setIsAdmin(TRUE)->save();
    $this->drupalGet('admin/reports/status');
    $assert_session->pageTextNotContains('Update notifications permission');
  }

}
