<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests adding and removing permissions via the UI.
 */
#[Group('user')]
#[RunTestsInSeparateProcesses]
class UserPermissionsAdminTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests granting and revoking permissions via the UI sorts permissions.
   */
  public function testPermissionsSorting(): void {
    $role = Role::create(['id' => 'test_role', 'label' => 'Test role']);
    // Start the role with a permission that is near the end of the alphabet.
    $role->grantPermission('view user email addresses');
    $role->save();

    $this->drupalLogin($this->drupalCreateUser([
      'administer permissions',
    ]));
    $this->drupalGet('admin/people/permissions');

    $this->assertSession()->statusCodeEquals(200);

    // Add a permission that is near the start of the alphabet.
    $this->submitForm([
      'test_role[change own username]' => 1,
    ], 'Save permissions');

    // Check that permissions are sorted alphabetically.
    $storage = \Drupal::entityTypeManager()->getStorage('user_role');
    /** @var \Drupal\user\Entity\Role $role */
    $role = $storage->loadUnchanged($role->id());
    $this->assertEquals([
      'change own username',
      'view user email addresses',
    ], $role->getPermissions());

    // Remove the first permission, resulting in a single permission in the
    // first key of the array.
    $this->submitForm([
      'test_role[change own username]' => 0,
    ], 'Save permissions');
    /** @var \Drupal\user\Entity\Role $role */
    $role = $storage->loadUnchanged($role->id());
    $this->assertEquals([
      'view user email addresses',
    ], $role->getPermissions());
  }

  /**
   * Confirms that PermissionsListFilter can filter the permissions UI listing.
   */
  public function testFilterPermissionsEvent(): void {
    \Drupal::service('module_installer')->install(['user_permissions_test', 'node']);
    $this->drupalCreateContentType(['type' => 'page']);
    $this->drupalLogin($this->drupalCreateUser([
      'administer permissions',
    ]));

    // Test UserPermissionsForm.
    $this->drupalGet('admin/people/permissions');
    $items = array_map(fn($item) => $item->getAttribute('for'),
      $this->getSession()->getPage()->findAll('css', 'tbody label[for^="edit-anonymous"], tbody label[for^="edit-authenticated"]'));

    // Just assert there are greater than 6 permissions available so we know
    // there are more overall permissions than what appears once the event
    // subscriber in `user_filtered_permissions_test` is running.
    $this->assertGreaterThan(6, $items);

    // Confirm the 'access content' permission was moved to node permissions,
    // inserted before 'view own unpublished content', the move that takes place
    // in \Drupal\user\Form\UserPermissionsForm::permissionsByProvider().
    $rows = $this->getSession()->getPage()->findAll('css', 'tbody tr[data-drupal-selector]');
    $node_permissions_index = 0;
    $system_permissions_index = 0;
    $access_content_index = 0;
    $view_own_unpublished_content_index = 0;

    foreach ($rows as $index => $row) {
      if ($row->getAttribute('data-drupal-selector') === 'edit-permissions-node') {
        $node_permissions_index = $index;
      }

      if ($row->getAttribute('data-drupal-selector') === 'edit-permissions-access-content') {
        $access_content_index = $index;
      }

      if ($row->getAttribute('data-drupal-selector') === 'edit-permissions-view-own-unpublished-content') {
        $view_own_unpublished_content_index = $index;
      }

      if ($row->getAttribute('data-drupal-selector') === 'edit-permissions-system') {
        $system_permissions_index = $index;
        break;
      }
    }

    $this->assertGreaterThan(3, $system_permissions_index - $node_permissions_index, 'The System permissions section appears after node permissions, with at least 3 items in between them.');
    $this->assertSame($access_content_index + 1, $view_own_unpublished_content_index, '"Access content" appears immediately before view unpublished content');
    $this->assertGreaterThan($node_permissions_index, $access_content_index, '"Access content" appears after the node permissions section label');
    $this->assertGreaterThan($access_content_index, $system_permissions_index, '"Access content" appears before the system permissions section label');

    // Test UserPermissionsRoleSpecificForm.
    $this->drupalGet('admin/people/permissions/authenticated');
    $items = array_map(fn($item) => $item->getText(),
      $this->getSession()->getPage()->findAll('css', 'tbody .title'));
    $this->assertGreaterThan(3, $items);

    // Test UserPermissionsModuleSpecificForm.
    $this->drupalGet('admin/people/permissions/module/user');
    $items = array_map(fn($item) => $item->getText(),
      $this->getSession()->getPage()->findAll('css', 'tbody .title'));
    $this->assertGreaterThan(3, $items);

    // Test EntityPermissionsForm.
    $this->drupalGet('admin/structure/types/manage/page/permissions');
    $items = array_map(fn($item) => $item->getText(),
      $this->getSession()->getPage()->findAll('css', 'tbody .title'));
    $this->assertGreaterThan(3, $items);

    // Enable a module that filters all but permissions a, b, c.
    \Drupal::service('module_installer')->install(['user_filtered_permissions_test']);
    $this->drupalGet('admin/people/permissions');
    $items = array_map(fn($item) => $item->getAttribute('for'),
      $this->getSession()->getPage()->findAll('css', 'tbody label[for^="edit-anonymous"], tbody label[for^="edit-authenticated"]'));
    sort($items);
    $this->assertCount(6, $items);

    $this->assertEquals([
      'edit-anonymous-a',
      'edit-anonymous-b',
      'edit-anonymous-c',
      'edit-authenticated-a',
      'edit-authenticated-b',
      'edit-authenticated-c',
    ], $items);

    $this->drupalGet('admin/people/permissions/authenticated');
    $items = array_map(fn($item) => $item->getText(),
      $this->getSession()->getPage()->findAll('css', 'tbody .title'));
    $this->assertCount(3, $items);
    $this->assertEquals([
      'Test permission',
      'Test permission',
      'Test permission',
    ], $items);

    $this->drupalGet('admin/people/permissions/module/user');
    $items = array_map(fn($item) => $item->getText(),
      $this->getSession()->getPage()->findAll('css', 'tbody .title'));
    $this->assertEmpty($items);

    $this->drupalGet('admin/structure/types/manage/page/permissions');
    $items = array_map(fn($item) => $item->getText(),
      $this->getSession()->getPage()->findAll('css', 'tbody .title'));
    $this->assertEmpty($items);

    // Test the form with all node permissions hidden to see how the edge case
    // of moving "access content" from system to node permissions works when no
    // other node permissions are present.
    \Drupal::state()->set('user_filtered_permissions_test.test_case', 'no node permissions');
    $this->drupalGet('admin/people/permissions');

    $rows = $this->getSession()->getPage()->findAll('css', 'tbody tr[data-drupal-selector]');
    $node_permissions_index = -1;
    $system_permissions_index = -1;
    $access_content_index = -1;
    $view_own_unpublished_content_index = -1;
    foreach ($rows as $index => $row) {
      if ($row->getAttribute('data-drupal-selector') === 'edit-permissions-node') {
        $node_permissions_index = $index;
      }

      if ($row->getAttribute('data-drupal-selector') === 'edit-permissions-access-content') {
        $access_content_index = $index;
      }

      if ($row->getAttribute('data-drupal-selector') === 'edit-permissions-view-own-unpublished-content') {
        $view_own_unpublished_content_index = $index;
      }

      if ($row->getAttribute('data-drupal-selector') === 'edit-permissions-system') {
        $system_permissions_index = $index;
      }
    }

    $this->assertGreaterThan(-1, $node_permissions_index, 'A node permissions section exists');
    $this->assertSame($node_permissions_index + 1, $access_content_index, '"Access content" is the first permission in node permissions');
    $this->assertSame($node_permissions_index + 2, $system_permissions_index, 'The system permissions section starts after the "access content" node permission' . $rows[$node_permissions_index + 2]->getHtml());
    $this->assertSame(-1, $view_own_unpublished_content_index, 'There is no "view own unpublished content" permission');

    // Test the form with "view own published content" hidden to see how the edge
    // case of moving "access content" from system to node permissions works
    // without that permission as "view own published content" is typically used
    // as the reference point for positioning the moved "access content."
    \Drupal::state()->set('user_filtered_permissions_test.test_case', 'no view own published content');
    $this->drupalGet('admin/people/permissions');

    $rows = $this->getSession()->getPage()->findAll('css', 'tbody tr[data-drupal-selector]');
    $node_permissions_index = -1;
    $system_permissions_index = -1;
    $access_content_index = -1;
    $view_own_unpublished_content_index = -1;
    foreach ($rows as $index => $row) {
      if ($row->getAttribute('data-drupal-selector') === 'edit-permissions-node') {
        $node_permissions_index = $index;
      }

      if ($row->getAttribute('data-drupal-selector') === 'edit-permissions-access-content') {
        $access_content_index = $index;
      }

      if ($row->getAttribute('data-drupal-selector') === 'edit-permissions-view-own-unpublished-content') {
        $view_own_unpublished_content_index = $index;
      }

      if ($row->getAttribute('data-drupal-selector') === 'edit-permissions-system') {
        $system_permissions_index = $index;
      }
    }

    $this->assertGreaterThan(-1, $node_permissions_index, 'A node permissions section exists');
    $this->assertGreaterThan($node_permissions_index, $system_permissions_index, 'The system permissions section appears after node permissions');
    $this->assertSame($access_content_index, $system_permissions_index - 1, 'View published content is the last item in node permissions, since there is no "view own published content" to to precede');
    $this->assertSame(-1, $view_own_unpublished_content_index, 'There is no permission to view own unpublished content');
  }

}
