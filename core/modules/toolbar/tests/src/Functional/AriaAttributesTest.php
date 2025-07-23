<?php

declare(strict_types=1);

namespace Drupal\Tests\toolbar\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the ARIA attributes of the toolbar.
 */
#[Group('toolbar')]
class AriaAttributesTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['toolbar'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test that attributes related to faux buttons are not present.
   *
   * This tests that `aria-expanded`, `aria-role`, etc not are present on the
   * toolbar tray trigger when JavaScript is disabled.
   *
   * This test must be accompanied by a functional test with JavaScript
   * to confirm that the attributes *are* present when JavaScript is enabled.
   *
   * @see \Drupal\Tests\toolbar\FunctionalJavaScript\AriaAttributesTest::testAriaAttributesArePresentOnTrigger()
   */
  public function testAriaAttributesNotPresentOnTrigger(): void {
    $admin_user = $this->drupalCreateUser([
      'access toolbar',
    ]);
    $this->drupalLogin($admin_user);

    // For consistency with xxx test, use element whose tray is
    // not expanded by default.
    $user_link = $this
      ->getSession()
      ->getPage()
      ->find('css', '#toolbar-item-user');

    // Check that `role` is not present.
    $this->assertNull($user_link->getAttribute('role'));

    // Check that `aria-expanded` is not present.
    $this->assertNull($user_link->getAttribute('aria-expanded'));
  }

}
