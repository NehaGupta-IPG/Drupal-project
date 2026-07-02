<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the treegrid focus model: the row, not the handle, is the cursor.
 *
 * The WAI-ARIA treegrid contract requires the focused element to be the one
 * carrying the row semantics (level, position, expanded state), and the whole
 * tree to be navigated with the arrow keys from a single roving tab stop. These
 * tests pin that contract:
 *  - exactly one row is the tab stop; handles are never tab stops;
 *  - arrow keys move focus between <tr> rows and the roving tabindex follows;
 *  - the focused row exposes aria-level/posinset/setsize;
 *  - aria-selected marks a row only while it is picked up for a move;
 *  - clicking a row focuses it;
 *  - the treegrid is described by a visible interaction hint.
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
   * The tree is a single tab stop on a row; handles are never tab stops.
   */
  public function testSingleRowTabStop(): void {
    $rows_at_zero = (int) $this->getSession()->evaluateScript(
      "return document.querySelectorAll('drupal-treegrid tr[role=\"row\"][tabindex=\"0\"]').length;",
    );
    $this->assertSame(1, $rows_at_zero, 'Exactly one row is the tab stop.');

    // The handle is a pointer/drag affordance: it carries no tabindex (so it is
    // never focusable) and is hidden from assistive technology.
    $focusable_handles = (int) $this->getSession()->evaluateScript(
      "return document.querySelectorAll('drupal-treegrid .menu-tree-ui-row__handle[tabindex]').length;",
    );
    $this->assertSame(0, $focusable_handles, 'No handle is focusable.');

    $exposed_handles = (int) $this->getSession()->evaluateScript(
      "return document.querySelectorAll('drupal-treegrid .menu-tree-ui-row__handle:not([aria-hidden=\"true\"])').length;",
    );
    $this->assertSame(0, $exposed_handles, 'Every handle is hidden from assistive technology.');
  }

  /**
   * Arrow keys move focus between rows, and the roving tabindex follows.
   */
  public function testArrowKeysFocusRows(): void {
    $this->pressKeyOnRow('B', 'ArrowDown');

    $this->assertSame('TR', $this->activeTag(), 'Focus lands on a row, not a handle.');
    $this->assertSame($this->linkIds['A'], $this->activeRowId(), 'Focus moved to the next row.');

    $this->assertSame('0', $this->row('A')->getAttribute('tabindex'), 'The newly focused row is the tab stop.');
    $this->assertSame('-1', $this->row('B')->getAttribute('tabindex'), 'The previous row is no longer the tab stop.');
  }

  /**
   * The focused row exposes the hierarchy a screen reader needs to announce.
   */
  public function testFocusedRowExposesHierarchy(): void {
    // ArrowDown from the first (leaf) row lands on A: top level, 2nd of 2.
    $this->pressKeyOnRow('B', 'ArrowDown');

    $level = $this->getSession()->evaluateScript(
      "return document.activeElement.getAttribute('aria-level');",
    );
    $pos = $this->getSession()->evaluateScript(
      "return document.activeElement.getAttribute('aria-posinset');",
    );
    $size = $this->getSession()->evaluateScript(
      "return document.activeElement.getAttribute('aria-setsize');",
    );
    $this->assertSame('1', $level, 'The focused element carries aria-level.');
    $this->assertSame('2', $pos, 'The focused element carries aria-posinset.');
    $this->assertSame('2', $size, 'The focused element carries aria-setsize.');
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
   * Toggling a row announces it by name, not by the screen reader's row number.
   *
   * A screen reader labels an aria-expanded change positionally ("row N",
   * counting the header row); the tree posts a live-region message naming the
   * row instead. The expanded/collapsed state is left to the screen reader
   * (from aria-expanded), so the message carries only the name.
   */
  public function testToggleAnnouncesRowName(): void {
    // Focus the parent row A, then normalize it to collapsed.
    $this->pressKeyOnRow('B', 'ArrowDown');
    if ($this->row('A')->getAttribute('aria-expanded') === 'true') {
      $this->pressKeyOnRow('A', 'ArrowLeft');
    }

    $this->pressKeyOnRow('A', 'ArrowRight');
    $this->assertAnnounced('Item A');
  }

  /**
   * Plain navigation selects nothing; only a picked-up row is selected.
   */
  public function testSelectionOnlyWhileGrabbed(): void {
    $this->pressKeyOnRow('A', 'ArrowDown');
    $this->assertSame(
      0,
      (int) $this->getSession()->evaluateScript(
        "return document.querySelectorAll('drupal-treegrid tr[aria-selected=\"true\"]').length;",
      ),
      'Navigating does not select a row.',
    );

    $this->pressKeyOnRow('A', ' ');
    $this->assertSame(
      'true',
      $this->row('A')->getAttribute('aria-selected'),
      'The picked-up row is selected.',
    );

    $this->pressKeyOnRow('A', 'Escape');
    $this->assertSame(
      0,
      (int) $this->getSession()->evaluateScript(
        "return document.querySelectorAll('drupal-treegrid tr[aria-selected=\"true\"]').length;",
      ),
      'Cancelling the move clears the selection.',
    );
  }

  /**
   * Clicking a row moves the cursor (focus) to it.
   */
  public function testClickingRowFocusesIt(): void {
    $this->row('B')->find('css', '.menu-tree-ui-row__title')->click();

    $this->assertSame('TR', $this->activeTag(), 'A row click focuses the row.');
    $this->assertSame($this->linkIds['B'], $this->activeRowId(), 'The clicked row is focused.');
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
   * The tree is one Tab stop: only the active row's controls are tabbable.
   */
  public function testOnlyActiveRowControlsAreTabbable(): void {
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
  }

  /**
   * The tabbable control set follows the cursor as it moves between rows.
   */
  public function testTabbableSetFollowsCursor(): void {
    $this->assertGreaterThan(0, $this->activeOperationCount());

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
   * Expand toggles are operated from the row, never a Tab stop themselves.
   */
  public function testExpandToggleIsNotATabStop(): void {
    $tabbable = (int) $this->getSession()->evaluateScript(
      "return Array.from(document.querySelectorAll('drupal-treegrid button.menu-tree-ui-row__toggle'))"
      . ".filter((b) => b.getAttribute('tabindex') !== '-1').length;",
    );
    $this->assertSame(0, $tabbable, 'No expand toggle is in the tab order.');
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

  /**
   * Asserts the live-region announcer eventually carries the given text.
   *
   * @param string $needle
   *   The expected substring of the announcement.
   */
  private function assertAnnounced(string $needle): void {
    $needle_json = json_encode($needle);
    // Null-safe: the live region is created lazily on the first announcement,
    // so the expression must return false (not throw) until it exists.
    $this->assertJsCondition(
      "((document.getElementById('drupal-live-announce') || {}).textContent || '')"
      . ".includes($needle_json)",
    );
  }

}
