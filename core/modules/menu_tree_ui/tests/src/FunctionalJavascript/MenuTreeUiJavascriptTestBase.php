<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;

/**
 * Base class for menu_tree_ui reorder/gesture JavaScript tests.
 *
 * Holds the shared fixture builder and tree-inspection helpers used by the
 * drag-and-drop, keyboard-move and save/cancel suites. Subclasses provide their
 * own seedMenu() and mix in the gesture drivers they need
 * (MenuTreeUiDragGestureTrait, MenuTreeUiKeyboardMoveTrait).
 */
abstract class MenuTreeUiJavascriptTestBase extends WebDriverTestBase {

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
   * Plugin IDs of seeded menu links keyed by their test label.
   *
   * @var array<string, string>
   */
  protected array $linkIds = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->seedMenu();
    $admin = $this->drupalCreateUser(['administer menu']);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/structure/menu/manage/test/tree');
    // Wait until the <drupal-treegrid> custom element has at least one top-level
    // row in DOM — that's the "page upgraded" signal.
    $this->assertSession()->waitForElement(
      'css',
      'drupal-treegrid table[role="treegrid"] tbody tr[role="row"]',
    );
    // Reorder wiring is deferred until the CSRF token fetch resolves; wait for
    // it so a move is not dispatched before the drag controller is listening.
    $this->assertSession()->waitForElement(
      'css',
      'drupal-treegrid[data-treegrid-reorder-ready="true"]',
    );
  }

  /**
   * Seeds the menu fixture the test operates on.
   *
   * Implementations create a menu with id 'test' and populate $this->linkIds
   * with the plugin IDs of the links they reference, via createLink().
   */
  abstract protected function seedMenu(): void;

  /**
   * Creates one menu_link_content entity and returns its plugin ID.
   */
  protected function createLink(string $title, string $parent_plugin_id, int $weight): string {
    $entity = MenuLinkContent::create([
      'menu_name' => 'test',
      'title' => $title,
      'link' => ['uri' => 'route:<nolink>'],
      'parent' => $parent_plugin_id,
      'weight' => $weight,
    ]);
    $entity->save();
    return $entity->getPluginId();
  }

  /**
   * Creates the menu entity the fixture links belong to.
   */
  protected function createTestMenu(): void {
    Menu::create([
      'id' => 'test',
      'label' => 'Test',
    ])->save();
  }

  /**
   * Returns the DOM row for a labelled link, or NULL if not in DOM.
   */
  protected function row(string $label): ?NodeElement {
    $id = $this->linkIds[$label] ?? NULL;
    if ($id === NULL) {
      return NULL;
    }
    return $this->getSession()->getPage()->find(
      'css',
      sprintf('tr[role="row"][data-id="%s"]', $id),
    );
  }

  /**
   * Returns the test label for a plugin ID, or NULL if unknown.
   */
  protected function labelFor(string $plugin_id): ?string {
    $label = array_search($plugin_id, $this->linkIds, TRUE);
    return $label === FALSE ? NULL : (string) $label;
  }

  /**
   * Builds the CSS selector for the direct child rows of a parent.
   *
   * @param ?string $parent_label
   *   The parent label, or NULL for the top level.
   *
   * @return string
   *   The row selector.
   */
  protected function childRowsSelector(?string $parent_label): string {
    $parent_id = $parent_label === NULL
      ? ''
      : ($this->linkIds[$parent_label] ?? throw new \InvalidArgumentException("Unknown label $parent_label"));
    return sprintf('tr[role="row"][data-parent-id="%s"]', $parent_id);
  }

  /**
   * Expands a row by clicking its toggle button and waits for the open state.
   */
  protected function expandRow(string $label): void {
    $row = $this->row($label);
    $this->assertNotNull($row, "Row $label not in DOM.");
    $toggle = $row->find('css', '.menu-tree-ui-row__name button.menu-tree-ui-row__toggle');
    $this->assertNotNull($toggle, "Row $label has no toggle button.");
    $toggle->click();
    // After expand, the parent is open and at least one child row exists as a
    // sibling carrying data-parent-id = the parent's plugin ID.
    $this->assertSession()->waitForElement(
      'css',
      sprintf('tr[role="row"][data-id="%s"][aria-expanded="true"]', $this->linkIds[$label]),
    );
    $this->assertSession()->waitForElement(
      'css',
      sprintf('tr[role="row"][data-parent-id="%s"]', $this->linkIds[$label]),
    );
  }

  /**
   * Taps a row's drag handle: pointerdown + pointerup with no movement.
   *
   * Stays below the 5px drag threshold, so it fires the controller's tap path
   * rather than a drag — a pickup when idle, a drop when a move is running.
   */
  protected function tapHandle(string $label): void {
    $id = $this->linkIds[$label] ?? throw new \InvalidArgumentException("Unknown label: $label");
    $script = <<<JS
      const row = document.querySelector('tr[role="row"][data-id="$id"]');
      if (!row) throw new Error('row not in DOM');
      row.scrollIntoView({block: 'center'});
      const handle = row.querySelector('.menu-tree-ui-row__handle:not(.menu-tree-ui-row__handle--locked)');
      if (!handle) throw new Error('no draggable handle on row');
      const r = handle.getBoundingClientRect();
      const x = r.left + r.width / 2;
      const y = r.top + r.height / 2;
      function send(type) {
        handle.dispatchEvent(new PointerEvent(type, {
          bubbles: true, cancelable: true, composed: true,
          pointerId: 1, isPrimary: true, button: 0,
          clientX: x, clientY: y, screenX: x, screenY: y,
        }));
      }
      send('pointerdown');
      send('pointerup');
    JS;
    $this->getSession()->executeScript($script);
  }

  /**
   * Waits until any committed move's async refresh work has settled.
   */
  protected function settle(): void {
    $this->getSession()->wait(
      5000,
      '!document.querySelector(\'drupal-treegrid[data-treegrid-moving="true"]\')'
      . ' && !document.querySelector(\'.menu-tree-ui-row--grabbed\')'
      . ' && !document.querySelector(\'[data-loading="true"]\')',
    );
  }

  /**
   * Asserts a labelled row's depth (aria-level) matches the expected value.
   */
  protected function assertDepth(string $label, int $expected): void {
    $id = $this->linkIds[$label] ?? NULL;
    $this->assertNotNull($id, "Unknown label $label.");
    // The reorder commit is server-first; wait for the row to settle at its new
    // depth so a slower CI commit does not race the assertion.
    $this->getSession()->wait(
      10000,
      sprintf(
        "((r) => !!r && r.getAttribute('aria-level') === %s)(document.querySelector(%s))",
        json_encode((string) $expected),
        json_encode(sprintf('tr[role="row"][data-id="%s"]', $id)),
      ),
    );
    $row = $this->row($label);
    $this->assertNotNull($row, "Row $label not in DOM.");
    $this->assertSame(
      (string) $expected,
      $row->getAttribute('aria-level'),
      "Row $label should be at level $expected.",
    );
  }

  /**
   * Asserts the row's inline indent matches the template formula for its depth.
   *
   * Template formula: `padding-inline-start: (depth - 1) * 1.25rem`. Chromium
   * defaults to 16px so we compare in resolved pixels — deterministic
   * regardless of how the inline rem value is serialized.
   */
  protected function assertIndent(string $label, int $expected_depth): void {
    $id = $this->linkIds[$label] ?? NULL;
    $this->assertNotNull($id, "Unknown label $label.");
    $script = sprintf(
      "return window.getComputedStyle(document.querySelector('tr[role=\"row\"][data-id=\"%s\"] .menu-tree-ui-row__name')).paddingInlineStart;",
      $id,
    );
    $actual = (string) $this->getSession()->evaluateScript($script);
    $expected_px = ($expected_depth - 1) * 1.25 * 16;
    $expected = sprintf('%dpx', (int) $expected_px);
    $this->assertSame(
      $expected,
      $actual,
      "Row $label at depth $expected_depth: expected padding-inline-start $expected, got $actual.",
    );
  }

  /**
   * Asserts a row presents as a parent (chevron-toggle + has-children).
   */
  protected function assertIsParentRow(string $label): void {
    $row = $this->row($label);
    $this->assertNotNull($row, "Row $label not in DOM.");
    $this->assertSame(
      'true',
      $row->getAttribute('data-has-children'),
      "Row $label should advertise data-has-children=\"true\".",
    );
    $toggle = $row->find(
      'css',
      '.menu-tree-ui-row__name > button.menu-tree-ui-row__toggle',
    );
    $this->assertNotNull(
      $toggle,
      "Row $label should have a real <button> toggle (not a spacer).",
    );
    // The toggle is operated from the row (Left/Right/Enter), not via Tab, so it
    // sits outside the page tab order — including when a move promotes a leaf to
    // a parent and the toggle is built client-side. Expand/collapse stays
    // keyboard-reachable through the row, keeping the tree a single tab stop.
    $this->assertSame(
      '-1',
      $toggle->getAttribute('tabindex'),
      "Row $label's toggle should be tabindex=-1 (operated from the row).",
    );
  }

  /**
   * Asserts a row presents as a leaf (spacer toggle + no has-children).
   */
  protected function assertIsLeafRow(string $label): void {
    $row = $this->row($label);
    $this->assertNotNull($row, "Row $label not in DOM.");
    $this->assertSame(
      'false',
      $row->getAttribute('data-has-children'),
      "Row $label should advertise data-has-children=\"false\".",
    );
    $this->assertNull(
      $row->find(
        'css',
        '.menu-tree-ui-row__name > button.menu-tree-ui-row__toggle',
      ),
      "Row $label should no longer have a <button> toggle.",
    );
    $spacer = $row->find(
      'css',
      '.menu-tree-ui-row__name > span.menu-tree-ui-row__toggle--spacer',
    );
    $this->assertNotNull(
      $spacer,
      "Row $label should have a spacer in its toggle slot.",
    );
  }

  /**
   * Asserts the number of direct child rows of a parent.
   *
   * Rows are flat siblings; direct children carry data-parent-id = the parent's
   * plugin ID (or '' for the top level). Pass NULL for the top level. For a
   * leaf — or a former parent demoted to leaf after losing its only child — the
   * expected count must be 0.
   */
  protected function assertChildCount(?string $parent_label, int $expected): void {
    if ($parent_label === NULL) {
      $selector = 'drupal-treegrid tbody tr[role="row"][data-parent-id=""]';
      $where = 'top-level';
    }
    else {
      $id = $this->linkIds[$parent_label] ?? throw new \InvalidArgumentException("Unknown label $parent_label");
      $selector = sprintf(
        'drupal-treegrid tbody tr[role="row"][data-parent-id="%s"]',
        $id,
      );
      $where = "under $parent_label";
    }
    // Wait for the server-first reorder to settle the child count.
    $this->getSession()->wait(
      10000,
      sprintf('document.querySelectorAll(%s).length === %d', json_encode($selector), $expected),
    );
    $count = (int) $this->getSession()->evaluateScript(
      sprintf("return document.querySelectorAll(%s).length;", json_encode($selector)),
    );
    $this->assertSame(
      $expected,
      $count,
      "Expected $expected direct rows $where, got $count.",
    );
  }

  /**
   * Asserts every `data-id` in the tree is unique.
   *
   * Catches refresh / move bugs that leave a row in its source position while
   * also adding it to the destination.
   */
  protected function assertNoDuplicateRows(): void {
    $ids = $this->getSession()->evaluateScript(
      'return Array.from(document.querySelectorAll(\'drupal-treegrid tr[role="row"][data-id]\')).map(r => r.dataset.id);',
    );
    $this->assertIsArray($ids);
    $counts = array_count_values($ids);
    $dupes = array_filter($counts, static fn (int $c): bool => $c > 1);
    $this->assertEmpty(
      $dupes,
      'Duplicate rows in DOM: ' . json_encode($dupes),
    );
  }

  /**
   * Asserts no row currently in the test viewport is marked virtualized.
   *
   * The IntersectionObserver virtualization in windowing.js sets
   * `data-virtualized="true"` to hide content of off-screen rows and reserve
   * fixed height. When the move pipeline re-observes already-visible rows, they
   * can get stuck with that flag set — appearing as empty rows even though
   * they're in the viewport. This catches that. Our fixtures are small enough
   * that every row fits in the viewport, so there should be no legitimately
   * off-screen rows after a move.
   */
  protected function assertNoVirtualizedRows(): void {
    // Wait for the IntersectionObserver to clear the flag on visible rows,
    // rather than guessing a fixed delay that flakes on a loaded runner.
    $this->getSession()->wait(
      2000,
      'Array.from(document.querySelectorAll(\'drupal-treegrid tr[role="row"][data-virtualized="true"]\')).length === 0',
    );
    $virtualized = $this->getSession()->evaluateScript(
      'return Array.from(document.querySelectorAll(\'drupal-treegrid tr[role="row"][data-virtualized="true"]\')).map(r => r.dataset.id);',
    );
    $this->assertSame(
      [],
      $virtualized,
      'Rows stuck marked data-virtualized="true" after the move (will render as empty): '
      . json_encode($virtualized),
    );
  }

  /**
   * Asserts every row in DOM has its title element populated.
   *
   * Catches "empty row" regressions: a row whose cells were wiped or never
   * built, leaving a `<tr>` shell with no visible label.
   */
  protected function assertNoBlankRows(): void {
    $blanks = $this->getSession()->evaluateScript(<<<'JS'
      return Array.from(document.querySelectorAll('drupal-treegrid tr[role="row"]'))
        .filter(r => {
          const t = r.querySelector('.menu-tree-ui-row__title');
          return !t || !t.textContent || t.textContent.trim() === '';
        })
        .map(r => r.dataset.id || '(no data-id)');
    JS
    );
    $this->assertSame([], $blanks, 'Found blank rows: ' . json_encode($blanks));
  }

  /**
   * Asserts the ordered DOM rows that are direct children of a parent.
   *
   * Rows are flat siblings in one <tbody>; a row's parent is read from
   * data-parent-id, and document order in the table is the visual order. The
   * commit is server-first: rows reorder only once the POST + refresh lands, so
   * this waits for the expected order before asserting to avoid racing a slower
   * commit on CI.
   *
   * @param string[] $expected_labels
   *   Labels in the order they should appear as direct children.
   * @param string $row_selector
   *   CSS matching the direct child rows (a `tr[data-parent-id="…"]` set).
   */
  protected function assertSiblings(array $expected_labels, string $row_selector): void {
    $expected_ids = array_map(
      fn (string $label): string => $this->linkIds[$label] ?? '',
      $expected_labels,
    );
    $this->getSession()->wait(
      10000,
      sprintf(
        "Array.from(document.querySelectorAll(%s)).map((r) => r.dataset.id).join(',') === %s",
        json_encode($row_selector),
        json_encode(implode(',', $expected_ids)),
      ),
    );
    $rows = $this->getSession()->getPage()->findAll('css', $row_selector);
    $actual = [];
    foreach ($rows as $row) {
      $actual[] = $this->labelFor($row->getAttribute('data-id')) ?? '?';
    }
    $this->assertSame($expected_labels, $actual, "Sibling order matching $row_selector.");
  }

  /**
   * Asserts a row is immediately followed by another within a row set.
   *
   * Rows are flat siblings in one <tbody>; document order in the table is the
   * visual order. The commit is server-first: rows reorder only once the
   * POST + refresh lands, so this waits for the pair to become adjacent
   * before asserting, to avoid racing a slower commit on CI.
   *
   * @param string $before
   *   The label expected immediately before $after.
   * @param string $after
   *   The label expected immediately after $before.
   * @param string $row_selector
   *   CSS matching the row set to search within (a `tr[data-parent-id="…"]`
   *   set).
   */
  protected function assertAdjacent(string $before, string $after, string $row_selector): void {
    $before_id = $this->linkIds[$before] ?? throw new \InvalidArgumentException("Unknown label $before");
    $after_id = $this->linkIds[$after] ?? throw new \InvalidArgumentException("Unknown label $after");
    $this->getSession()->wait(
      10000,
      sprintf(
        '(() => { const ids = Array.from(document.querySelectorAll(%s))'
        . '.map((r) => r.dataset.id); const i = ids.indexOf(%s);'
        . ' return i !== -1 && ids[i + 1] === %s; })()',
        json_encode($row_selector),
        json_encode($before_id),
        json_encode($after_id),
      ),
    );
    $ids = array_map(
      static fn (NodeElement $row): ?string => $row->getAttribute('data-id'),
      $this->getSession()->getPage()->findAll('css', $row_selector),
    );
    $index = array_search($before_id, $ids, TRUE);
    $this->assertNotFalse($index, "Row $before not found matching $row_selector.");
    $this->assertSame(
      $after_id,
      $ids[$index + 1] ?? NULL,
      "Row $after should immediately follow row $before matching $row_selector.",
    );
  }

}
