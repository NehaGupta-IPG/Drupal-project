<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that row labels are the same plain text on every page of rows.
 *
 * Rows on the first page and rows loaded later come from the same template and
 * must give the same accessible name, including for a title holding markup and
 * an ampersand.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiRowLabelTest extends MenuTreeUiJavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'menu_link_content',
    'menu_tree_ui',
    'menu_tree_ui_test',
  ];

  /**
   * A link title that is markup and holds an ampersand.
   */
  private const TITLE = 'Fish & <b>Chips</b>';

  /**
   * {@inheritdoc}
   */
  protected function seedMenu(): void {
    $this->createTestMenu();
    $this->linkIds['P'] = $this->createLink('Parent P', '', 0);
    $this->linkIds['T'] = $this->createLink(self::TITLE, '', 1);
    $this->linkIds['R'] = $this->createLink('Leaf R', '', 2);
    $this->linkIds['C'] = $this->createLink(self::TITLE, $this->linkIds['P'], 0);
    $this->linkIds['G'] = $this->createLink('Grandchild G', $this->linkIds['C'], 0);
    $this->linkIds['TP'] = $this->createLink(self::TITLE, '', 3);
    $this->linkIds['TG'] = $this->createLink('Grandchild TG', $this->linkIds['TP'], 0);
  }

  /**
   * A loaded row is named the same as a row of the first page.
   */
  public function testLoadedRowNameMatchesPageRow(): void {
    $this->expandRow('P');
    $page_name = $this->row('T')->getAttribute('aria-label');
    $this->assertSame(self::TITLE, $page_name);
    $this->assertSame($page_name, $this->row('C')->getAttribute('aria-label'));
  }

  /**
   * A loaded row's toggle is named the same as a first-page row's toggle.
   */
  public function testLoadedToggleNameMatchesPageToggle(): void {
    $this->expandRow('P');
    $selector = '.menu-tree-ui-row__name button.menu-tree-ui-row__toggle';
    $page_name = $this->row('TP')->find('css', $selector)->getAttribute('aria-label');
    $loaded_name = $this->row('C')->find('css', $selector)->getAttribute('aria-label');
    $this->assertStringContainsString(self::TITLE, $page_name);
    $this->assertSame($page_name, $loaded_name);
  }

  /**
   * A stored title with markup is escaped on page rows and loaded rows.
   */
  public function testStoredMarkupInTitleIsEscaped(): void {
    $this->expandRow('P');

    foreach (['T', 'C'] as $label) {
      $title = $this->row($label)->find('css', '.menu-tree-ui-row__title');
      $this->assertNotNull($title, "Row $label shows its title.");
      $this->assertFalse($title->has('css', 'b'), "Row $label prints no element from its title.");
      $this->assertStringContainsString('<b>', $title->getText(), "Row $label shows the markup as text.");
    }
  }

  /**
   * A loaded locked row names the title as plain text.
   */
  public function testLoadedLockedRowNameIsPlainText(): void {
    Menu::create(['id' => 'mtu-test-locked', 'label' => 'Locked fixture'])->save();
    $this->drupalGet('/admin/structure/menu/manage/mtu-test-locked/tree');
    $parent = $this->assertSession()->waitForElement('css', 'tr[data-id="menu_tree_ui_test.locked_parent"] [data-treegrid-toggle]');
    $this->assertNotNull($parent, 'The fixture menu shows its parent.');
    $parent->click();

    $child = $this->assertSession()->waitForElement('css', 'tr[data-id="menu_tree_ui_test.locked_child"]');
    $this->assertNotNull($child, 'The locked child loads.');
    $label = $child->getAttribute('aria-label');
    $this->assertStringStartsWith(self::TITLE, $label);
    $this->assertStringNotContainsString('&amp;', $label);
    $this->assertStringNotContainsString('&lt;', $label);
  }

}
