<?php

declare(strict_types=1);

namespace Drupal\Tests\toolbar\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests toolbar menu with non-link items.
 */
#[Group('toolbar')]
#[RunTestsInSeparateProcesses]
class ToolbarMenuNoLinkTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['toolbar', 'menu_ui', 'test_page_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the expansion button for <nolink> items.
   */
  public function testToolbarNoLinkExpansion(): void {

    $admin_user = $this->drupalCreateUser([
      'access toolbar',
      'access administration pages',
      'administer menu',
    ]);
    $this->drupalLogin($admin_user);

    // Create the <nolink> item (which renders a <span>).
    $parent = MenuLinkContent::create([
      'title' => 'Span Parent',
      'link' => ['uri' => 'route:<nolink>'],
      'menu_name' => 'admin',
      'parent' => 'system.admin',
      'expanded' => TRUE,
    ]);
    $parent->save();

    // A child item is required for the expansion button to appear.
    $parent_plugin_id = 'menu_link_content:' . $parent->uuid();
    MenuLinkContent::create([
      'title' => 'Child Item',
      'link' => ['uri' => 'route:<front>'],
      'menu_name' => 'admin',
      'parent' => $parent_plugin_id,
    ])->save();

    // Load the page.
    $this->drupalGet('<front>');
    $assert_session = $this->assertSession();

    // Wait for the toolbar to finish loading. By default it will be horizontal.
    $assert_session->waitForElementVisible('css', 'body.toolbar-horizontal');
    // The administration tray is open by default.
    $assert_session->waitForElementVisible('css', '#toolbar-item-administration-tray');

    // Switch to vertical orientation.
    $orientation_button = $this->assertSession()->buttonExists('Vertical orientation');
    $this->assertNotNull($orientation_button, 'The "Vertical orientation" button was found.');
    $orientation_button->click();

    // Wait for the toolbar to become vertical.
    $assert_session->waitForElementVisible('css', 'body.toolbar-vertical #toolbar-item-administration-tray.toolbar-tray-vertical');

    // Now, assert the button exists and is visible.
    // We look for the `div` that has our `span`, then find the button inside.
    $span = $assert_session->elementExists('xpath', "//span[contains(text(), 'Span Parent')]");
    $xpath_button = "//div[span[contains(text(), 'Span Parent')]]/button[contains(@class, 'toolbar-handle')]";
    $handle_button = $assert_session->waitForElement('xpath', $xpath_button);
    $this->assertNotNull($span, 'The Span Parent (<nolink>) item exists in the DOM.');
    $this->assertNotNull($handle_button, 'The expansion button1 for the <nolink> item exists in the DOM.');

    $this->assertTrue($handle_button->isVisible(), 'The expansion button for the <nolink> item is visible.');

    // (Optional but recommended) Verify that clicking the button opens the submenu.
    $handle_button->click();
    $child_link = $assert_session->waitForElementVisible('named', ['link', 'Child Item']);
    $this->assertNotNull($child_link, 'The child menu item becomes visible after clicking the expansion button.');

  }

}
