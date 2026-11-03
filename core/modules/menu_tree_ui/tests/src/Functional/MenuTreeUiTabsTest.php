<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Functional;

use Drupal\Core\Url;
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

    // Edit is the settings page, Links is the tree and Links (no js) is the
    // classic overview.
    $this->assertLinkToPath('/admin/structure/menu/manage/tabs-test');
    $this->assertLinkToPath('/admin/structure/menu/manage/tabs-test/tree');
    $this->assertLinkToPath('/admin/structure/menu/manage/tabs-test/links');

    // The settings page itself does not carry the link overview.
    $this->assertSession()->elementNotExists('css', '#menu-overview');
  }

  /**
   * The tree route is denied to a user without menu administration access.
   *
   * Every tree route requires _entity_access: 'menu.update', granted by the
   * 'administer menu' permission; a user without it must be refused.
   */
  public function testTreeRouteRequiresAccess(): void {
    $this->drupalLogin($this->drupalCreateUser(['access administration pages']));
    $this->drupalGet('/admin/structure/menu/manage/tabs-test/tree');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * The Menus list dropbutton has Edit links (tree), Edit links (no js), Edit.
   */
  public function testListDropbutton(): void {
    $this->drupalGet('/admin/structure/menu');
    // The primary action is the tree, the no-JS action is the classic page
    // and Edit is the settings page.
    $this->assertLinkToPath('/admin/structure/menu/manage/tabs-test/tree');
    $this->assertLinkToPath('/admin/structure/menu/manage/tabs-test/links');
    $this->assertLinkToPath('/admin/structure/menu/manage/tabs-test');
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
   *
   * The path is a site-relative user path; the base path is prepended so the
   * match holds when the test site runs in a subdirectory.
   */
  private function linkToPathXpath(string $path): string {
    return $this->assertSession()->buildXPathQuery(
      '//a[@href = :href or starts-with(@href, concat(:href, "?"))]',
      [':href' => Url::fromUserInput($path)->toString()],
    );
  }

}
