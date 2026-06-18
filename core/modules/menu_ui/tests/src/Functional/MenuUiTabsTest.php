<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_ui\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the menu tabs and the settings/links split.
 *
 * Without the tree UI, a menu has two tabs: "Edit" (settings) and "Links" (the
 * classic tabledrag overview). The menu's settings no longer carry the link
 * list. See https://www.drupal.org/project/drupal/issues/2520232.
 */
#[Group('menu_ui')]
#[RunTestsInSeparateProcesses]
final class MenuUiTabsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'menu_ui'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalPlaceBlock('local_tasks_block');
    Menu::create(['id' => 'tabs-test', 'label' => 'Tabs test'])->save();
    $this->drupalLogin($this->drupalCreateUser([
      'administer menu',
      'access administration pages',
    ]));
  }

  /**
   * The menu shows Edit and Links tabs, and settings hold no link list.
   */
  public function testTwoTabs(): void {
    $this->drupalGet('/admin/structure/menu/manage/tabs-test');
    $assert = $this->assertSession();

    $assert->linkExists('Edit');
    $assert->linkExists('Links');
    // No tree, so no "Links (no js)" fallback tab.
    $assert->linkNotExists('Links (no js)');
    $assert->linkByHrefExists('/admin/structure/menu/manage/tabs-test/links');

    // The settings page only carries settings, not the link overview.
    $assert->fieldExists('label');
    $assert->elementNotExists('css', '#menu-overview');
  }

  /**
   * The Links tab carries the overview table and the Add link action.
   */
  public function testLinksTab(): void {
    $this->drupalGet('/admin/structure/menu/manage/tabs-test/links');
    $assert = $this->assertSession();
    $assert->elementExists('css', '#menu-overview');
    $assert->linkExists('Add link');
  }

}
