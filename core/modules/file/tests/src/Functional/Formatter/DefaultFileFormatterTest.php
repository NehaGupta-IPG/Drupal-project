<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Functional\Formatter;

use Drupal\Tests\views_ui\Functional\UITestBase;

/**
 * Tests the default file formatter.
 *
 * @see \Drupal\file\Plugin\Field\FieldFormatter\DefaultFileFormatter
 * @group file
 */
class DefaultFileFormatterTest extends UITestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field', 'file', 'file_test_views'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test views to enable.
   */
  public static array $testViews = ['test_file_to_node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = []): void {
    parent::setUp($import_test_views, $modules);
    $this->adminUser = $this->drupalCreateUser([
      'administer views',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests that configuration form doesn't have duplicate form elements.
   */
  public function testConfigurationForm(): void {
    $this->drupalGet('admin/structure/views/nojs/handler/test_file_to_node/default/field/filename');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('options[type]');
    $this->assertSession()->fieldNotExists('options[settings][type]');
    $this->assertSession()->pageTextContainsOnce('Name of the file with no path components.');
  }

}
