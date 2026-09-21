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

    // Edit is the settings page and Links is the link overview. Without the
    // tree there is no third tab.
    $this->assertLinkToPath('/admin/structure/menu/manage/tabs-test');
    $this->assertLinkToPath('/admin/structure/menu/manage/tabs-test/links');
    $this->assertNoLinkToPath('/admin/structure/menu/manage/tabs-test/tree');

    // The settings page only carries settings, not the link overview.
    $assert->fieldExists('label');
    $assert->elementNotExists('css', '#menu-overview');
  }

  /**
   * The Menus list dropbutton leads with "Edit links", then "Edit" settings.
   */
  public function testListDropbutton(): void {
    $this->drupalGet('/admin/structure/menu');
    // Edit links is the link overview and Edit is the settings page. Without
    // the tree there is no separate no-JS action.
    $this->assertLinkToPath('/admin/structure/menu/manage/tabs-test/links');
    $this->assertLinkToPath('/admin/structure/menu/manage/tabs-test');
    $this->assertNoLinkToPath('/admin/structure/menu/manage/tabs-test/tree');
  }

  /**
   * The Links tab carries the overview table and the Add link action.
   */
  public function testLinksTab(): void {
    $this->drupalGet('/admin/structure/menu/manage/tabs-test/links');
    $this->assertSession()->elementExists('css', '#menu-overview');
    $this->assertLinkToPath('/admin/structure/menu/manage/tabs-test/add');
  }

  /**
   * Asserts a link to a path, with or without a destination query.
   */
  protected function assertLinkToPath(string $path): void {
    $this->assertSession()->elementExists('xpath', $this->linkToPathXpath($path));
  }

  /**
   * Asserts there is no link to a path, with or without a destination query.
   */
  protected function assertNoLinkToPath(string $path): void {
    $this->assertSession()->elementNotExists('xpath', $this->linkToPathXpath($path));
  }

  /**
   * Builds the XPath matching an anchor whose href is $path or "$path?...".
   */
  private function linkToPathXpath(string $path): string {
    return $this->assertSession()->buildXPathQuery(
      '//a[@href = :path or starts-with(@href, concat(:path, "?"))]',
      [':path' => $path],
    );
  }

}
