<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the menu tabs when the tree UI is enabled.
 *
 * With menu_tree_ui on, a menu has three tabs: "Edit" (settings), "Links" (the
 * scalable tree), and "Links (no js)" (the classic tabledrag fallback).
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiTabsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'menu_link_content',
    'menu_tree_ui',
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
    $this->drupalPlaceBlock('local_tasks_block');
    Menu::create(['id' => 'tabs-test', 'label' => 'Tabs test'])->save();
    $this->drupalLogin($this->drupalCreateUser([
      'administer menu',
      'access administration pages',
    ]));
  }

  /**
   * The menu shows Edit, Links (tree) and Links (no js) tabs.
   */
  public function testThreeTabs(): void {
    $this->drupalGet('/admin/structure/menu/manage/tabs-test');
    $assert = $this->assertSession();

    $assert->linkExists('Edit');
    $assert->linkExists('Links');
    $assert->linkExists('Links (no js)');

    // The "Links" tab is the tree; "Links (no js)" is the classic overview.
    $assert->linkByHrefExists('/admin/structure/menu/manage/tabs-test/tree');
    $assert->linkByHrefExists('/admin/structure/menu/manage/tabs-test/links');

    // The settings page itself no longer lists links.
    $assert->pageTextNotContains('There are no menu links yet');
  }

}
