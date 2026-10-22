<?php

namespace Drupal\Tests\toolbar\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests toolbar menu navigation.
 *
 * @group toolbar
 */
class ToolbarMenuNavigationTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'block',
    'toolbar',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $user = $this->createUser([
      'administer blocks',
      'access toolbar',
      'administer nodes',
      'access administration pages',
      'administer site configuration',
      'access content overview',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests that toolbar submenu items are accessible by hovering a parent.
   */
  public function testToolbarMenus() {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $this->getSession()->resizeWindow(1200, 600);
    $this->drupalGet('<front>');

    $configuration_tab = $page->find('css', 'div.toolbar-menu-administration > ul > li:nth-child(3)');
    $configuration_menu = $configuration_tab->find('css', '.toolbar-menu');

    // Confirm the tab text is correct since it was selected with nth-child and
    // the item in that position could change.
    $this->assertTrue(strpos($configuration_tab->getText(), 'Configuration') === 0);
    $this->assertFalse($configuration_tab->hasClass('hover-intent'));
    $this->assertFalse($configuration_menu->isVisible());

    $this->getSession()->getPage()->find('css', '#toolbar-link-system-admin_config')->mouseOver();
    $assert_session->waitForElementVisible('css', 'div.toolbar-menu-administration > ul > li:nth-child(3).hover-intent');
    $this->assertTrue($configuration_menu->isVisible());

    $system_submenu_link = $configuration_tab->find('css', 'ul > li:nth-child(2)');
    $this->assertTrue(strpos($system_submenu_link->getText(), 'System') === 0);
    $system_submenu_menu = $system_submenu_link->find('css', '.toolbar-menu');
    $this->assertFalse($system_submenu_menu->isVisible());
    $this->getSession()->getPage()->find('css', '#toolbar-link-system-admin_config_system')->mouseOver();
    $assert_session->waitForElementVisible('css', 'div.toolbar-menu-administration > ul > li:nth-child(3).hover-intent > ul > li:nth-child(2).hover-intent');
    $this->assertTrue($system_submenu_menu->isVisible());

    // Confirm the menu text since it was selected with nth-child and the item
    // in that position could change.
    $this->assertSame('Basic site settings Cron', trim($system_submenu_menu->getText()));
  }

}
