<?php

declare(strict_types=1);

namespace Drupal\Tests\toolbar\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the ARIA attributes of the toolbar.
 */
#[Group('toolbar')]
class AriaAttributesTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['toolbar'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test that attributes related to faux buttons are present.
   *
   * This tests that `aria-expanded`, `aria-role`, etc are present on the
   * toolbar tray trigger by default when JavaScript is enabled.
   *
   * This test must be accompanied by a test without JavaScript
   * to confirm that the attributes are *not* present when JavaScript
   * is disabled.
   *
   * @see \Drupal\Tests\toolbar\Functional\AriaAttributesTest::testAriaAttributesNotPresentOnTrigger()
   */
  public function testAriaAttributesArePresentOnTrigger(): void {
    $admin_user = $this->drupalCreateUser([
      'access toolbar',
    ]);
    $this->drupalLogin($admin_user);
    $this->assertSession()->waitForElementRemoved('css', '.toolbar-loading');

    // Tests must be on item whose tray is not visible by default,
    // because the act of toggling the tray open will add `aria-expanded`
    // if it's not already present.
    $user_link = $this
      ->getSession()
      ->getPage()
      ->find('css', '#toolbar-item-user');

    // Check `role="button"`.
    $this->assertEquals(
      'button',
      $user_link->getAttribute('role')
    );

    // Check that `aria-expanded` is initially 'false'. This implies that
    // the attribute exists and is not ''.
    // If it's missing entirely, the actual will be NULL.
    $this->assertEquals(
      'false',
      $user_link->getAttribute('aria-expanded')
    );
  }

}
