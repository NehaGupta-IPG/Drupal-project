<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Functional\Views;

use Drupal\Tests\views\Functional\ViewTestBase;

/**
 * Tests the Roles filter.
 *
 * @group user
 */
class DuplicateRoleFilterTest extends ViewTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_duplicate_role_filter'];

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user', 'user_test_views', 'views_ui'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = ['user_test_views']): void {
    parent::setUp($import_test_views, $modules);

    $this->enableViewsTestModule();
  }

  /**
   * Tests filtering when the Roles filter is added twice.
   */
  public function testUserRolesFilter(): void {
    // Add two roles.
    $role1 = $this->createRole([], 'test_role_1');
    $role2 = $this->createRole([], 'test_role_2');
    // User with test_role_1 role.
    $user = $this->createUser([], 'User with role one');
    $user->addRole($role1)->save();

    // User with test_role_2 role.
    $second_user = $this->createUser([], 'User with role two');
    $second_user->addRole($role2)->save();

    // Log in as the administrator to access Views and perform the tests.
    $this->drupalLogin($this->drupalCreateUser([
      'administer users',
      'administer views',
      'access user profiles',
    ]));

    // Navigate to the view filter settings for roles and select the roles to
    // filter.
    $this->drupalGet('admin/structure/views/nojs/handler/duplicate_role_filter/page_1/filter/roles_target_id');
    $edit = [
      'options[value][]' => ['test_role_1', 'test_role_2'],
    ];
    $this->drupalGet('admin/structure/views/nojs/handler/duplicate_role_filter/page_1/filter/roles_target_id');
    $this->submitForm($edit, 'Apply');

    // Navigate to the duplicate role exposed filter in the view settings and
    // apply the same roles.
    $this->drupalGet('admin/structure/views/nojs/handler/duplicate_role_filter/page_1/filter/roles_target_id_1');
    $this->submitForm($edit, 'Apply');
    $this->drupalGet('admin/structure/views/view/duplicate_role_filter/edit/page_1');
    $this->submitForm([], 'Save');

    // Verify that we can save the view.
    $this->assertSession()->pageTextContains('The view Duplicate role filter has been saved.');
    $path = 'duplicate-role-filter';

    // Filter by "All" to display all users.
    $options['query'] = ['roles_target_id_1' => 'All'];
    $this->drupalGet($path, $options);
    // Assert that the page shows both users.
    $this->assertSession()->pageTextContains($user->getDisplayName());
    $this->assertSession()->pageTextContains($second_user->getDisplayName());

    // Filter by "test_role_1" to display only the user with role one.
    $options['query'] = ['roles_target_id_1' => 'test_role_1'];
    $this->drupalGet($path, $options);
    // Assert that only the user with role one appears.
    $this->assertSession()->pageTextContains($user->getDisplayName());
    $this->assertSession()->pageTextNotContains($second_user->getDisplayName());

    // Filter by "test_role_2" to display only the user with role two.
    $options['query'] = ['roles_target_id_1' => 'test_role_2'];
    $this->drupalGet($path, $options);
    // Assert that only the user with role two appears.
    $this->assertSession()->pageTextContains($second_user->getDisplayName());
    $this->assertSession()->pageTextNotContains($user->getDisplayName());
  }

}
