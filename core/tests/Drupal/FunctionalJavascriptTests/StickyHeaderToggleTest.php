<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests;

/**
 * Tests that the sticky header can be toggled.
 *
 * @group javascript
 */
class StickyHeaderToggleTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'views'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createContentType(['type' => 'page']);
    for ($i = 0; $i < 20; $i++) {
      $this->createNode(['title' => "Test page {$i}"]);
    }

    $user = $this->drupalCreateUser([
      'administer site configuration',
      'access content',
      'access content overview',
      'edit any page content',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests the checkbox for enabling/disabling table sticky header.
   */
  public function testStickyDisabled(): void {
    $this->drupalGet('admin/content');
    // Height is set to default value for all js tests.
    $this->getSession()->resizeWindow(1024, 768);
    $assert_session = $this->assertSession();
    $checkbox = $assert_session->elementExists('css', '.tableheader-toggle-sticky input[type="checkbox"]');

    // Confirm that the table header is sticky if the checkbox is checked.
    $this->assertTrue($checkbox->isChecked());
    $this->getSession()->evaluateScript('scroll(0, document.documentElement.scrollTop + 1500);');
    $assert_session->assertVisibleInViewport('css', 'table thead');

    // Confirm that the table header is not sticky if the checkbox is unchecked.
    $this->getSession()->evaluateScript('scroll(0, document.documentElement.scrollTop - 1500);');
    $checkbox->uncheck();
    $this->assertFalse($checkbox->isChecked());
    $this->getSession()->evaluateScript('scroll(0, document.documentElement.scrollTop + 1500);');
    $assert_session->assertNotVisibleInViewport('css', 'table thead');
  }

}
