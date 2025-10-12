<?php

declare(strict_types=1);

namespace Drupal\Tests\toolbar\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the JavaScript functionality of the toolbar.
 */
#[Group('toolbar')]
#[RunTestsInSeparateProcesses]
class ToolbarIntegrationTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['toolbar', 'node', 'toolbar_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests if the toolbar can be toggled with JavaScript.
   */
  public function testToolbarToggling(): void {
    $admin_user = $this->drupalCreateUser([
      'access toolbar',
      'administer site configuration',
      'access content overview',
    ]);
    $this->drupalLogin($admin_user);

    // Set size for horizontal toolbar.
    $this->getSession()->resizeWindow(1200, 600);
    $this->drupalGet('<front>');
    $this->assertNotEmpty($this->assertSession()->waitForElement('css', 'body.toolbar-horizontal'));
    $this->assertNotEmpty($this->assertSession()->waitForElementVisible('css', '.toolbar-tray'));

    $page = $this->getSession()->getPage();

    // Test that it is possible to toggle the toolbar tray.
    $content_link = $page->findLink('Content');
    $manage_link = $page->find('css', '#toolbar-item-administration');

    // Start with open tray.
    $this->waitAndAssertAriaPressedState($manage_link, TRUE);
    $this->assertTrue($content_link->isVisible(), 'Toolbar tray is open by default.');

    // Click to close.
    $manage_link->click();
    $this->waitAndAssertAriaPressedState($manage_link, FALSE);
    $this->assertFalse($content_link->isVisible(), 'Toolbar tray is closed after clicking the "Manage" link.');

    // Click to open.
    $manage_link->click();
    $this->waitAndAssertAriaPressedState($manage_link, TRUE);
    $this->assertTrue($content_link->isVisible(), 'Toolbar tray is visible again after clicking the "Manage" button a second time.');

    // Test toggling the toolbar tray between horizontal and vertical.
    $tray = $page->findById('toolbar-item-administration-tray');
    $this->assertFalse($tray->hasClass('toolbar-tray-vertical'), 'Toolbar tray is not vertically oriented by default.');
    $page->pressButton('Vertical orientation');
    $this->assertTrue($tray->hasClass('toolbar-tray-vertical'), 'After toggling the orientation the toolbar tray is now displayed vertically.');

    $page->pressButton('Horizontal orientation');
    $this->assertTrue($tray->hasClass('toolbar-tray-horizontal'), 'After toggling the orientation a second time the toolbar tray is displayed horizontally again.');
  }

  /**
   * Tests that the orientation toggle is not shown for empty toolbar items.
   */
  public function testEmptyTray(): void {
    // Granting access to the toolbar but not any administrative menu links will
    // result in an empty toolbar tray for the "Manage" toolbar item.
    $admin_user = $this->drupalCreateUser([
      'access toolbar',
    ]);
    $this->drupalLogin($admin_user);

    // Set size for horizontal toolbar.
    $this->getSession()->resizeWindow(1200, 600);
    $this->drupalGet('<front>');
    $this->assertNotEmpty($this->assertSession()->waitForElement('css', 'body.toolbar-horizontal'));
    $this->assertNotEmpty($this->assertSession()->waitForElementVisible('css', '.toolbar-tray'));

    // Test that the orientation toggle does not appear.
    $page = $this->getSession()->getPage();
    $tray = $page->findById('toolbar-item-administration-tray');
    $this->assertTrue($tray->hasClass('toolbar-tray-horizontal'), 'Toolbar tray is horizontally oriented by default.');
    $this->assertSession()->elementNotExists('css', '#toolbar-item-administration-tray .toolbar-menu');
    $this->assertSession()->elementNotExists('css', '#toolbar-item-administration-tray .toolbar-toggle-orientation');
    $button = $page->findButton('Vertical orientation');
    $this->assertFalse($button->isVisible(), 'Orientation toggle from other tray is not visible');
  }

  /**
   * Switching between tabs must not leave multiple tabs active.
   */
  public function testSwitchBetweenMultipleTabs(): void {
    $user = $this->drupalCreateUser([
      'access toolbar',
    ]);
    $this->drupalLogin($user);

    // Stable horizontal layout.
    $this->getSession()->resizeWindow(1280, 800);
    $this->drupalGet('<front>');

    $this->assertSession()->waitForElementRemoved('css', '.toolbar-loading');
    $this->assertNotEmpty($this->assertSession()->waitForElement('css', 'body.toolbar-horizontal, body.toolbar-vertical'));

    $page = $this->getSession()->getPage();

    // Grab three tabs.
    $admin_tab = $page->find('css', '#toolbar-item-administration');
    $user_tab = $page->find('css', '#toolbar-item-user');
    $toolbar_item_test = $page->find('css', '#toolbar-tab-testing');

    $this->assertNotNull($admin_tab, 'Administration tab present.');
    $this->assertNotNull($user_tab, 'User tab present.');
    $this->assertNotNull($toolbar_item_test, 'Test tab present.');

    // Normalize start: ensure Administration is active.
    if ($admin_tab->getAttribute('aria-pressed') !== 'true') {
      $admin_tab->click();
      $this->waitAndAssertAriaPressedState($admin_tab, TRUE);
    }
    $this->waitExactlyOne('.toolbar-bar .trigger[aria-pressed="true"]');
    $this->waitExactlyOne('.toolbar-bar .trigger.is-active');

    // Step 1: Switching from Administration to User/Account tab (should switch cleanly).
    $user_tab->click();
    $this->waitAndAssertAriaPressedState($user_tab, TRUE);
    $this->waitAndAssertAriaPressedState($admin_tab, FALSE);
    $this->waitExactlyOne('.toolbar-bar .trigger[aria-pressed="true"]');
    $this->waitExactlyOne('.toolbar-bar .trigger.is-active');

    // Step 2: Switching from User to Test tab tabs (this is where the bug shows without the patch).
    $toolbar_item_test->click();
    $this->waitAndAssertAriaPressedState($toolbar_item_test, TRUE);
    $this->waitAndAssertAriaPressedState($user_tab, FALSE);

    // If the bug exists, one (or both) of these will fail (2 pressed/active).
    $this->waitExactlyOne('.toolbar-bar .trigger[aria-pressed="true"]');
    $this->waitExactlyOne('.toolbar-bar .trigger.is-active');

    // Sanity: back to Administration, still only one active.
    $admin_tab->click();
    $this->waitAndAssertAriaPressedState($admin_tab, TRUE);
    $this->waitAndAssertAriaPressedState($toolbar_item_test, FALSE);
    $this->waitExactlyOne('.toolbar-bar .trigger[aria-pressed="true"]');
    $this->waitExactlyOne('.toolbar-bar .trigger.is-active');
  }

  /**
   * Asserts that an element's `aria-pressed` attribute matches expected state.
   *
   * Uses `waitFor()` to pause until either the condition is met or the timeout
   * of `1` second has passed.
   *
   * @param \Behat\Mink\Element\NodeElement $element
   *   The element to be tested.
   * @param bool $expected
   *   The expected value of `aria-pressed`, as a boolean.
   *
   * @throws ExpectationFailedException
   */
  private function waitAndAssertAriaPressedState(NodeElement $element, bool $expected): void {
    $this->assertTrue(
      $this
        ->getSession()
        ->getPage()
        ->waitFor(1, function () use ($element, $expected): bool {
          // Get boolean representation of `aria-pressed`.
          // TRUE if `aria-pressed="true"`, FALSE otherwise.
          $actual = $element->getAttribute('aria-pressed') == 'true';

          // Exit `waitFor()` when $actual == $expected.
          return $actual == $expected;
        })
    );
  }

  /**
   * Waits until **exactly one** element matches a CSS selector and asserts it.
   *
   * Uses `waitFor()` to poll the DOM until the condition becomes true
   * (exactly one match) or the timeout elapses. Helpful to ensure that only a
   * single toolbar item (or any target element) is in the expected state at a
   * given moment.
   *
   * @param string $selector
   *   The CSS selector to evaluate against the whole page.
   *   Example: '.toolbar-bar .trigger[aria-pressed="true"]'.
   * @param int $timeout
   *   Maximum time in seconds to wait for the condition. Defaults to 3.
   *
   * @throws \PHPUnit\Framework\ExpectationFailedException
   *   Thrown if, after the timeout, there is not exactly one matching element.
   *
   * @see \Behat\Mink\Element\DocumentElement::waitFor()
   */
  private function waitExactlyOne(string $selector, int $timeout = 3): void {
    $this->assertTrue(
      $this->getSession()->getPage()->waitFor($timeout, function () use ($selector): bool {
        return count($this->getSession()->getPage()->findAll('css', $selector)) === 1;
      }),
      "Expected exactly one match for selector: {$selector}"
    );
  }

}
