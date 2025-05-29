<?php

declare(strict_types=1);

namespace Drupal\Tests\filter\Functional;

use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;

/**
 * Tests that a warning message appears when dangerous HTML tags are allowed in a filter format.
 *
 * @group filter
 */
class FilterDangerousHTMLWarningTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['filter', 'filter_test'];

  /**
   * A user with administrative permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Drupal\filter\Entity\FilterFormat $filtered_html_format */
    $filtered_html_format = FilterFormat::load('filtered_html');
    $filtered_html_permission = $filtered_html_format->getPermissionName();
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, [$filtered_html_permission]);

    $this->adminUser = $this->drupalCreateUser(['administer modules', 'administer filters', 'administer site configuration']);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests that a warning message appears when dangerous HTML tags are allowed.
   */
  public function testDangerousHTMLWarning(): void {
    $format_id = 'filtered_html';

    // Check if no warnings exist when no dangerous tags are present
    $this->drupalGet('admin/config/content/formats/manage/' . $format_id);
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->statusMessageNotExists('warning');

    // Check if warning pops up when dangerous tags are present
    $edit = [
      'filters[filter_html][settings][allowed_html]' => '<iframe>',
    ];
    $this->drupalGet('admin/config/content/formats/manage/' . $format_id);
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->statusMessageContains('You have insecure HTML tags in your filter format.', 'warning');
  }

}
