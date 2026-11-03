<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests People sub-menu links (Permissions and Roles) in the navigation.
 *
 * Verifies that the Permissions and Roles menu links defined under People are
 * visible in the navigation sidebar for users with the 'administer permissions'
 * permission, and hidden from users without that permission.
 */
#[Group('navigation')]
#[RunTestsInSeparateProcesses]
class NavigationPeopleMenuLinksTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['navigation', 'test_page_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to manage permissions and access the navigation.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A user with access to navigation but without permission to manage roles.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $limitedUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // This user needs 'administer users' so People appears in the navigation
    // as a parent menu item, and 'administer permissions' so the Permissions
    // and Roles child links are accessible.
    $this->adminUser = $this->drupalCreateUser([
      'access navigation',
      'access administration pages',
      'administer users',
      'administer permissions',
    ]);

    // This user can see the navigation but lacks any people-management
    // permissions, so neither People nor its children should appear.
    $this->limitedUser = $this->drupalCreateUser([
      'access navigation',
    ]);
  }

  /**
   * Tests Permissions and Roles links visibility based on user permissions.
   */
  public function testPeopleMenuLinksVisibility(): void {
    $test_page_url = Url::fromRoute('test_page_test.test_page');

    // A user with 'administer permissions' (and 'administer users' to make the
    // People parent link accessible) should see both child links in the
    // navigation sidebar under People.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet($test_page_url);

    $this->assertSession()->linkByHrefExists('/admin/people/permissions');
    $this->assertSession()->linkByHrefExists('/admin/people/roles');

    // Verify the links appear with the correct titles.
    $permissions_link = $this->getSession()->getPage()->findLink('Permissions');
    $this->assertNotNull($permissions_link);
    $this->assertStringContainsString('/admin/people/permissions', $permissions_link->getAttribute('href'));

    $roles_link = $this->getSession()->getPage()->findLink('Roles');
    $this->assertNotNull($roles_link);
    $this->assertStringContainsString('/admin/people/roles', $roles_link->getAttribute('href'));

    // A user without 'administer permissions' should not see either link.
    $this->drupalLogin($this->limitedUser);
    $this->drupalGet($test_page_url);

    $this->assertSession()->linkByHrefNotExists('/admin/people/permissions');
    $this->assertSession()->linkByHrefNotExists('/admin/people/roles');
  }

  /**
   * Tests that Permissions and Roles links are children of People in the menu.
   */
  public function testPeopleMenuLinksAreChildrenOfPeople(): void {
    $test_page_url = Url::fromRoute('test_page_test.test_page');
    $this->drupalLogin($this->adminUser);
    $this->drupalGet($test_page_url);

    // The People link should be a popover (has children) in the navigation.
    $people_popover = $this->getSession()->getPage()->find(
      'css',
      '.toolbar-popover .toolbar-button__label'
    );
    $this->assertNotNull($people_popover);

    // Both Permissions and Roles links should be inside a toolbar popover menu,
    // confirming they are nested under a parent item (People).
    $permissions_link = $this->getSession()->getPage()->find(
      'css',
      '.toolbar-popover__menu a[href*="/admin/people/permissions"]'
    );
    $this->assertNotNull($permissions_link);

    $roles_link = $this->getSession()->getPage()->find(
      'css',
      '.toolbar-popover__menu a[href*="/admin/people/roles"]'
    );
    $this->assertNotNull($roles_link);
  }

}
