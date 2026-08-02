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
  protected static $modules = ['toolbar', 'node'];

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
   * Tests that the tab is not shown items with an empty tray.
   */
  public function testEmptyTray(): void {
    // Granting access to the toolbar but not any administrative menu links will
    // result in an empty toolbar tray for the "Manage" toolbar item.
    $admin_user = $this->drupalCreateUser([
      'access toolbar',
    ]);
    $this->drupalLogin($admin_user);

    $assert_session = $this->assertSession();

    // Set size for horizontal toolbar.
    $this->getSession()->resizeWindow(1200, 600);
    $this->drupalGet('<front>');
    $this->assertNotEmpty($assert_session->waitForElement('css', 'body.toolbar-horizontal'));
    $assert_session->elementsCount('css', '.toolbar-tray', 2);

    // The admin tray is empty due to the user having no permissions to access
    // any items it would provide. In those instances, the tab will have the
    // `.toolbar-tab--inert` class, so it and the child elements can be hidden.
    $admin_tab = $assert_session->elementExists('css', '.toolbar-tab.toolbar-tab--inert #toolbar-item-administration.toolbar-item');
    $admin_tray = $assert_session->elementExists('css', '.toolbar-tab.toolbar-tab--inert #toolbar-item-administration-tray.toolbar-tray');
    $this->assertFalse($admin_tab->isVisible());
    $this->assertFalse($admin_tray->isVisible());
    $this->assertEmpty(trim($admin_tray->find('css', '.toolbar-menu-administration')->getHtml()));

    // Confirm the user tray has content and thus is visible and does not have
    // the `.toolbar-tab--inert` class added to its corresponding tab.
    $user_tab = $assert_session->elementExists('css', '.toolbar-tab #toolbar-item-user.toolbar-item');
    $user_tray = $assert_session->elementExists('css', '.toolbar-tab #toolbar-item-user-tray.toolbar-tray');
    $this->assertTrue($user_tab->isVisible());
    $this->assertNotEmpty(trim($user_tray->find('css', '.toolbar-menu')->getHtml()));
    $assert_session->elementNotExists('css', '.toolbar-tab.toolbar-tab--inert #toolbar-item-user-tray.toolbar-tray');
    $assert_session->elementNotExists('css', '.toolbar-tab.toolbar-tab--inert #toolbar-item-user.toolbar-item');
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

}
