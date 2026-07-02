<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the menu's own wiring on top of the treegrid roving-tabindex spine.
 *
 * The roving-tabindex model itself (a single tab stop, arrow keys moving
 * focus between rows, aria-selected while a row is picked up) is covered by
 * the treegrid module's own suite. This suite pins what the menu adds on top
 * of it:
 *  - the menu's pointer handle and expand toggle stay out of the tab order;
 *  - the focused row is named by its link title;
 *  - the treegrid is described by a visible interaction hint;
 *  - the cursor's scroll offset clears the sticky Save/Cancel bar;
 *  - the set of tabbable row controls follows the cursor between rows.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiTreegridFocusTest extends WebDriverTestBase {

  use MenuTreeUiKeyboardNavTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
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
    $this->seedMenu();
    $admin = $this->drupalCreateUser(['administer menu']);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/structure/menu/manage/test/tree');
    $this->assertSession()->waitForElement(
      'css',
      'drupal-treegrid table[role="treegrid"] tbody tr[role="row"]',
    );
  }

  /**
   * The menu's drag handles and expand toggles stay out of the tab order.
   *
   * Handles and expand toggles are markup the menu emits on top of the
   * treegrid spine, so this suite pins that neither is ever a Tab stop: a
   * handle carries no tabindex and is hidden from assistive technology, and a
   * toggle button always carries tabindex="-1".
   */
  public function testHandlesAndTogglesStayOutOfTabOrder(): void {
    $focusable_handles = (int) $this->getSession()->evaluateScript(
      "return document.querySelectorAll('drupal-treegrid .menu-tree-ui-row__handle[tabindex]').length;",
    );
    $this->assertSame(0, $focusable_handles, 'No handle is focusable.');

    $exposed_handles = (int) $this->getSession()->evaluateScript(
      "return document.querySelectorAll('drupal-treegrid .menu-tree-ui-row__handle:not([aria-hidden=\"true\"])').length;",
    );
    $this->assertSame(0, $exposed_handles, 'Every handle is hidden from assistive technology.');

    $tabbable_toggles = (int) $this->getSession()->evaluateScript(
      "return Array.from(document.querySelectorAll('drupal-treegrid button.menu-tree-ui-row__toggle'))"
      . ".filter((b) => b.getAttribute('tabindex') !== '-1').length;",
    );
    $this->assertSame(0, $tabbable_toggles, 'No expand toggle is in the tab order.');
  }

  /**
   * The focused row announces one curated name, not its flattened controls.
   *
   * The row's aria-label is the link title, so a screen reader reads that (with
   * the level, position, and expanded state from the other ARIA attributes)
   * rather than concatenating the handle, toggle, title, and every column
   * control into one utterance.
   */
  public function testFocusedRowAnnouncesItsName(): void {
    $this->pressKeyOnRow('B', 'ArrowDown');

    $label = $this->getSession()->evaluateScript(
      "return document.activeElement.getAttribute('aria-label');",
    );
    $this->assertSame('Item A', $label, 'The row is named by its title alone.');

    // No row leaves its name to be computed from the controls inside it.
    $unnamed = (int) $this->getSession()->evaluateScript(
      "return Array.from(document.querySelectorAll('drupal-treegrid tr[role=\"row\"]'))"
      . ".filter((r) => !r.getAttribute('aria-label')).length;",
    );
    $this->assertSame(0, $unnamed, 'Every row has an accessible name.');
  }

  /**
   * The treegrid points at a visible, non-empty interaction hint.
   */
  public function testTreegridHasDescription(): void {
    $table = $this->getSession()->getPage()->find('css', 'table[role="treegrid"]');
    $described_by = (string) $table->getAttribute('aria-describedby');
    $this->assertNotSame('', $described_by, 'The treegrid has an aria-describedby.');

    $hint = $this->getSession()->getPage()->find('css', '#' . $described_by);
    $this->assertNotNull($hint, 'The aria-describedby target exists.');
    $hint_text = trim($hint->getText());
    $this->assertNotSame('', $hint_text, 'The interaction hint has text.');
    // The roving-tabindex model is non-obvious, so the hint must point out that
    // Tab reaches a row's controls.
    $this->assertStringContainsStringIgnoringCase('Tab', $hint_text, 'The hint mentions Tab reaches a row\'s controls.');
  }

  /**
   * The rows carry a scroll offset so the cursor clears the sticky action bar.
   *
   * Focusing a row lets the browser scroll it into view, which ignores the
   * sticky Save/Cancel bar; without an offset the cursor lands hidden under it.
   * menu-tree.js measures the bar into --mtu-sticky-offset, which the rows'
   * scroll-margin reads.
   */
  public function testCursorClearsStickyActionBar(): void {
    $bar_height = (int) $this->getSession()->evaluateScript(
      "return document.querySelector('.menu-tree-ui__action-bar').offsetHeight;",
    );
    $this->assertGreaterThan(0, $bar_height, 'The action bar has a height.');

    $offset = (string) $this->getSession()->evaluateScript(
      "return getComputedStyle(document.querySelector('.menu-tree-ui'))"
      . ".getPropertyValue('--mtu-sticky-offset').trim();",
    );
    $this->assertSame(
      $bar_height . 'px',
      $offset,
      'Rows offset their scroll position by the sticky bar height.',
    );
  }

  /**
   * The set of tabbable row controls follows the cursor as it moves.
   *
   * Only the active row's operations are reachable by Tab; moving the cursor
   * to a new row shifts that set rather than leaving stray tabbable controls
   * on the row that lost focus.
   */
  public function testRowControlsFollowCursor(): void {
    $this->assertSame(
      0,
      (int) $this->getSession()->evaluateScript($this->strayTabbableScript()),
      'No control outside the active row is in the tab order.',
    );
    $this->assertGreaterThan(
      0,
      $this->activeOperationCount(),
      'The active row exposes its operations to Tab.',
    );

    $this->pressKeyOnRow('B', 'ArrowDown');

    $this->assertSame(
      0,
      (int) $this->getSession()->evaluateScript($this->strayTabbableScript()),
      'Moving the cursor left no stray tabbable controls behind.',
    );
    $this->assertGreaterThan(
      0,
      $this->activeOperationCount(),
      'The newly active row exposes its operations to Tab.',
    );
  }

  /**
   * JS that counts tabbable row controls outside the active row.
   *
   * @return string
   *   An expression returning the stray-control count.
   */
  private function strayTabbableScript(): string {
    return <<<JS
      (() => {
        const sel = 'a.menu-tree-ui-row__title, .treegrid-cell-checkbox,'
          + ' .menu-tree-ui-row__operations a, .menu-tree-ui-row__operations button';
        const active = document.querySelector('drupal-treegrid tr[role="row"][tabindex="0"]');
        return Array.from(document.querySelectorAll('drupal-treegrid ' + sel))
          .filter((c) => c.getAttribute('tabindex') !== '-1')
          .filter((c) => c.closest('tr[role="row"]') !== active)
          .length;
      })()
    JS;
  }

  /**
   * The number of tabbable operation controls in the active row.
   *
   * @return int
   *   The count.
   */
  private function activeOperationCount(): int {
    return (int) $this->getSession()->evaluateScript(<<<JS
      (() => {
        const active = document.querySelector('drupal-treegrid tr[role="row"][tabindex="0"]');
        if (!active) return 0;
        return Array.from(active.querySelectorAll(
          '.menu-tree-ui-row__operations a, .menu-tree-ui-row__operations button',
        )).filter((c) => c.getAttribute('tabindex') === '0').length;
      })()
    JS);
  }

  /**
   * Seeds a small menu with a leaf first and a parent second.
   *
   * Top level: B (leaf), then A (with A1, A2 (with A2a)). B is deliberately a
   * leaf placed first so that ArrowDown from it always lands on A, regardless of
   * the "auto-expand for very small menus" behavior: a leaf has no children to
   * descend into, so the row after it is always the next top-level item.
   */
  private function seedMenu(): void {
    Menu::create(['id' => 'test', 'label' => 'Test'])->save();
    $this->linkIds['B'] = $this->createLink('Item B', '', 0);
    $this->linkIds['A'] = $this->createLink('Item A', '', 1);
    $this->linkIds['A1'] = $this->createLink('Item A1', $this->linkIds['A'], 0);
    $this->linkIds['A2'] = $this->createLink('Item A2', $this->linkIds['A'], 1);
    $this->linkIds['A2a'] = $this->createLink('Item A2a', $this->linkIds['A2'], 0);
  }

}
