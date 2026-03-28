<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Functional;

use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies deprecated role permissions are displayed correctly.
 */
#[Group('user')]
#[RunTestsInSeparateProcesses]
class UserDeprecatedPermissionsTest extends BrowserTestBase {

  /**
   * User with admin privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * User's role ID.
   *
   * @var string
   */
  protected $rid;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user_deprecated_permissions_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer permissions',
      'administer site configuration',
    ]);

    // Find the new role ID.
    $all_rids = $this->adminUser->getRoles();
    unset($all_rids[array_search(RoleInterface::AUTHENTICATED_ID, $all_rids)]);
    $this->rid = reset($all_rids);
  }

  /**
   * Tests display of deprecated permissions in permission overview form.
   */
  public function testDeprecatedPermissionDisplay(): void {
    $this->drupalLogin($this->adminUser);
    $this->grantPermissions(Role::load($this->rid), ['f', 'i']);

    $this->drupalGet('admin/people/permissions');
    $row = $this->xpath('//tr[@data-drupal-selector="edit-permissions-e"]');
    // Check class of table row.
    $this->assertStringContainsString('deprecated', $row[0]->getAttribute('class'));
    $warning = $this->xpath('//tr[@data-drupal-selector="edit-permissions-e"]//em');
    $this->assertCount(1, $warning);
    $this->assertSame('The "e" permission is deprecated in drupal:12.0.0 and is removed from drupal:12.1.0. There is no replacement.', $warning[0]->getText());
    // Checkboxes for all roles should be disabled.
    $this->assertSession()->elementAttributeExists('css', '[name="anonymous[e]"]', 'disabled');
    $this->assertSession()->elementAttributeExists('css', '[name="authenticated[e]"]', 'disabled');
    $this->assertSession()->elementAttributeExists('css', '[name="' . $this->rid . '[e]"]', 'disabled');

    $row = $this->xpath('//tr[@data-drupal-selector="edit-permissions-f"]');
    // Check class of table row.
    $this->assertStringContainsString('deprecated', $row[0]->getAttribute('class'));
    $warning = $this->xpath('//tr[@data-drupal-selector="edit-permissions-f"]//em');
    $this->assertCount(1, $warning);
    $this->assertSame('The "f" permission is deprecated in drupal:12.0.0 and is removed from drupal:12.1.0. There is no replacement.', $warning[0]->getText());
    // Checkboxes for anonymous and authenticated role should be disabled.
    $this->assertSession()->elementAttributeExists('css', '[name="anonymous[f]"]', 'disabled');
    $this->assertSession()->elementAttributeExists('css', '[name="authenticated[f]"]', 'disabled');
    // Checkbox for current role should be checked and enabled.
    $this->assertSession()->checkboxChecked($this->rid . '[f]');
    $this->assertSession()->elementAttributeNotExists('css', '[name="' . $this->rid . '[f]"]', 'disabled');

    $row = $this->xpath('//tr[@data-drupal-selector="edit-permissions-h"]');
    // Check class of table row.
    $this->assertStringContainsString('deprecated', $row[0]->getAttribute('class'));
    $warning = $this->xpath('//tr[@data-drupal-selector="edit-permissions-h"]//em');
    $this->assertCount(2, $warning);
    $this->assertSame('Warning: Give to trusted roles only; this permission has security implications.', $warning[0]->getText());
    $this->assertSame('The "h" permission is deprecated in drupal:12.0.0 and is removed from drupal:12.1.0. There is no replacement.', $warning[1]->getText());
    // Checkboxes for all roles should be disabled.
    $this->assertSession()->elementAttributeExists('css', '[name="anonymous[h]"]', 'disabled');
    $this->assertSession()->elementAttributeExists('css', '[name="authenticated[h]"]', 'disabled');
    $this->assertSession()->elementAttributeExists('css', '[name="' . $this->rid . '[h]"]', 'disabled');

    $row = $this->xpath('//tr[@data-drupal-selector="edit-permissions-i"]');
    // Check class of table row.
    $this->assertStringContainsString('deprecated', $row[0]->getAttribute('class'));
    $warning = $this->xpath('//tr[@data-drupal-selector="edit-permissions-i"]//em');
    $this->assertCount(2, $warning);
    $this->assertSame('Warning: Give to trusted roles only; this permission has security implications.', $warning[0]->getText());
    $this->assertSame('The "i" permission is deprecated in drupal:12.0.0 and is removed from drupal:12.1.0. There is no replacement.', $warning[1]->getText());
    // Checkboxes for anonymous and authenticated role should be disabled.
    $this->assertSession()->elementAttributeExists('css', '[name="anonymous[i]"]', 'disabled');
    $this->assertSession()->elementAttributeExists('css', '[name="authenticated[i]"]', 'disabled');
    // Checkbox for current role should be checked and enabled.
    $this->assertSession()->checkboxChecked($this->rid . '[i]');
    $this->assertSession()->elementAttributeNotExists('css', '[name="' . $this->rid . '[i]"]', 'disabled');
  }

}
