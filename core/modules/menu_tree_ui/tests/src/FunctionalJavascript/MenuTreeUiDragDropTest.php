<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the menu_tree_ui drag-and-drop behavior end-to-end.
 *
 * Each scenario fires synthetic pointer events at the real DragController
 * pipeline, then verifies the move via the overlaid DOM:
 *  - DOM: the moved row's `aria-level` matches the destination level.
 *  - DOM: sibling order inside the destination container is correct.
 *
 * Moves are no longer written to storage on drop; they accumulate in a
 * server-side working copy until saved, so these tests assert the overlaid
 * tree rather than the persisted entity. Persistence is covered by
 * MenuTreeUiSaveCancelTest.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiDragDropTest extends WebDriverTestBase {

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
   * Plugin IDs of seeded menu links keyed by single-letter label.
   *
   * Labels used through the fixture: A, B, C, D, L, A1, A2, A2a, D1.
   *
   * @var array<string, string>
   */
  private array $linkIds = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->seedMenu();
    $admin = $this->drupalCreateUser(['administer menu']);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/structure/menu/manage/test/tree');
    // Wait until the <menu-tree> custom element has at least one top-level
    // row in DOM — that's the "page upgraded" signal.
    $this->assertSession()->waitForElement(
      'css',
      'menu-tree table[role="treegrid"] tbody tr[role="row"]'
    );
  }

  /**
   * Seeds a known menu tree.
   *
   * Top level: A (parent), B (leaf), C (leaf), D (parent), L (leaf).
   * A children: A1 (leaf), A2 (parent of A2a).
   * D children: D1 (leaf). D stays collapsed by default.
   */
  private function seedMenu(): void {
    Menu::create([
      'id' => 'test',
      'label' => 'Test',
    ])->save();

    $weight = 0;
    foreach (['A', 'B', 'C', 'D', 'L'] as $label) {
      $this->linkIds[$label] = $this->createLink($label, '', $weight++);
    }
    $this->linkIds['A1']  = $this->createLink('A1', $this->linkIds['A'], 0);
    $this->linkIds['A2']  = $this->createLink('A2', $this->linkIds['A'], 1);
    $this->linkIds['A2a'] = $this->createLink('A2a', $this->linkIds['A2'], 0);
    $this->linkIds['D1']  = $this->createLink('D1', $this->linkIds['D'], 0);
  }

  /**
   * Creates one menu_link_content entity and returns its plugin ID.
   */
  private function createLink(string $title, string $parent_plugin_id, int $weight): string {
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
   * Drop C in the top zone of B → order becomes A, C, B, D, L.
   */
  public function testDropBeforeSibling(): void {
    $this->drag('C', 'B', 'before');
    $this->assertSiblings(['A', 'C', 'B', 'D', 'L'], $this->childRowsSelector(NULL));
    $this->assertDepth('C', 1);
    $this->assertIndent('C', 1);
  }

  /**
   * Drop B in the bottom zone of C → order becomes A, C, B, D, L.
   */
  public function testDropAfterSibling(): void {
    $this->drag('B', 'C', 'after');
    $this->assertSiblings(['A', 'C', 'B', 'D', 'L'], $this->childRowsSelector(NULL));
    $this->assertDepth('B', 1);
    $this->assertIndent('B', 1);
  }

  /**
   * A pointer-only move commits on a handle tap, with no keyboard step.
   *
   * Exercises the single-pointer path WCAG 2.5.7 requires: tapping C's handle
   * picks it up, clicking the top of B aims it before B, and tapping C's handle
   * again drops it. Order becomes A, C, B, D, L without ever pressing Enter.
   */
  public function testPointerOnlyMoveCommitsOnHandleTap(): void {
    $this->tapHandle('C');
    $this->aimAtRow('B', 'before');
    $this->tapHandle('C');
    $this->getSession()->wait(
      5000,
      '!document.querySelector(\'menu-tree[data-mtu-moving="true"]\')'
      . ' && !document.querySelector(\'.menu-tree-ui-row--grabbed\')'
      . ' && !document.querySelector(\'[data-loading="true"]\')',
    );
    $this->assertSiblings(['A', 'C', 'B', 'D', 'L'], $this->childRowsSelector(NULL));
    $this->assertDepth('C', 1);
  }

  /**
   * Drop B in the middle of expanded A → B becomes A's first child at level 2.
   *
   * Dropping "on" a parent lands the row as the first child (right under the
   * parent header, where the drop is aimed), not the last.
   */
  public function testDropAsChildOfExpandedParent(): void {
    $this->expandRow('A');
    $this->drag('B', 'A', 'child');
    $this->assertDepth('B', 2);
    $this->assertIndent('B', 2);
    $this->assertSiblings(['B', 'A1', 'A2'], $this->childRowsSelector('A'));
    // Top level should now exclude B.
    $this->assertSiblings(['A', 'C', 'D', 'L'], $this->childRowsSelector(NULL));
  }

  /**
   * Drop B in the middle of leaf L → L is promoted to a parent containing B.
   *
   * The leaf should pick up a real toggle button so the new subtree can be
   * collapsed/expanded after the drop — not a placeholder spacer span.
   */
  public function testDropAsChildOfLeaf(): void {
    $this->drag('B', 'L', 'child');
    $this->assertDepth('B', 2);
    $this->assertIndent('B', 2);
    $this->assertIsParentRow('L');
  }

  /**
   * Drop B in the middle of collapsed D → D auto-expands, B becomes its child.
   */
  public function testDropAsChildOfCollapsedParentAutoExpands(): void {
    $this->drag('B', 'D', 'child');
    $this->assertDepth('B', 2);
    $this->assertIndent('B', 2);
    $this->assertSame(
      'true',
      $this->row('D')->getAttribute('aria-expanded'),
      'D should be expanded after a child-drop landed inside it.',
    );
    // D1 (pre-existing child) should still be inside D alongside the moved B.
    $children = $this->getSession()->getPage()->findAll(
      'css',
      $this->childRowsSelector('D'),
    );
    $labels = array_map(fn ($el) => $this->labelFor($el->getAttribute('data-id')), $children);
    $this->assertContains('D1', $labels, 'D1 should remain a child of D.');
    $this->assertContains('B', $labels, 'B should appear as a child of D.');
  }

  /**
   * Dragging a child out of its parent: A2a lifted to top level (after L).
   *
   * After A2a is moved, A2 has no remaining children. The UI should reflect
   * that — `data-has-children` flipping to "false", `aria-expanded` cleared,
   * and the toggle button swapped back to a spacer — otherwise the chevron
   * stays clickable on an empty subtree.
   */
  public function testDropChildOutOfParent(): void {
    $this->expandRow('A');
    $this->expandRow('A2');
    $this->drag('A2a', 'L', 'after');

    $this->assertDepth('A2a', 1);
    $this->assertIndent('A2a', 1);
    $this->assertSiblings(['A', 'B', 'C', 'D', 'L', 'A2a'], $this->childRowsSelector(NULL));

    $this->assertIsLeafRow('A2');
  }

  /**
   * Hovering over a collapsed parent for one second mid-drag auto-expands it.
   *
   * No pointerup is fired — we only assert the parent opened. The drag is
   * then cancelled so subsequent tests aren't polluted.
   */
  public function testHoverOverCollapsedParentExpands(): void {
    $source_id = $this->linkIds['B'];
    $target_id = $this->linkIds['D'];

    $start_drag = <<<JS
      const source = document.querySelector('tr[role="row"][data-id="$source_id"]');
      const target = document.querySelector('tr[role="row"][data-id="$target_id"]');
      target.scrollIntoView({block: 'center'});
      const handle = source.querySelector('.menu-tree-ui-row__handle:not(.menu-tree-ui-row__handle--locked)');
      const hRect = handle.getBoundingClientRect();
      const tRect = target.getBoundingClientRect();
      const startX = hRect.left + hRect.width / 2;
      const startY = hRect.top + hRect.height / 2;
      const endX = tRect.left + tRect.width / 2;
      const endY = tRect.top + tRect.height * 0.5;
      function send(t, type, x, y) {
        t.dispatchEvent(new PointerEvent(type, {
          bubbles: true, cancelable: true, composed: true,
          pointerId: 1, isPrimary: true, button: 0,
          clientX: x, clientY: y, screenX: x, screenY: y,
        }));
      }
      window.__mtuTestHandle = handle;
      send(handle, 'pointerdown', startX, startY);
      send(handle, 'pointermove', startX + 10, startY + 10);
      send(handle, 'pointermove', endX, endY);
    JS;
    $this->getSession()->executeScript($start_drag);

    // 1s timer + render slack.
    $opened = $this->assertSession()->waitForElement(
      'css',
      sprintf('tr[role="row"][data-id="%s"][aria-expanded="true"]', $target_id),
      3000,
    );
    $this->assertNotNull($opened, 'Collapsed parent should auto-expand after a 1s hover during drag.');

    // Cancel the drag so we don't leave a half-finished gesture.
    $this->getSession()->executeScript(
      'window.__mtuTestHandle.dispatchEvent(new PointerEvent("pointercancel", { bubbles: true, pointerId: 1 }));'
    );
  }

  /**
   * Dropping a row onto itself is a no-op; the gesture never reaches commit.
   */
  public function testCannotDropOntoSelf(): void {
    $this->drag('A', 'A', 'after');
    $this->assertSiblings(['A', 'B', 'C', 'D', 'L'], $this->childRowsSelector(NULL));
  }

  /**
   * Dropping a row onto its own descendant is blocked client-side.
   */
  public function testCannotDropOntoDescendant(): void {
    $this->expandRow('A');
    $this->drag('A', 'A1', 'after');
    $this->assertSiblings(['A', 'B', 'C', 'D', 'L'], $this->childRowsSelector(NULL));
  }

  /**
   * Sibling-reorder must not leave stray rows behind.
   *
   * Catches "phantom row" regressions: e.g., the moved row in DOM twice, or
   * a leftover blank `<li>` after a refresh that didn't clean its orphans.
   */
  public function testRowCountsAfterSameLevelReorder(): void {
    $this->drag('C', 'B', 'before');
    $this->assertChildCount(NULL, 5);
    $this->assertNoDuplicateRows();
    $this->assertNoBlankRows();
    $this->assertNoVirtualizedRows();
  }

  /**
   * Dropping into an expanded parent grows it by 1, shrinks source by 1.
   *
   * No row may appear in both groups.
   */
  public function testRowCountsAfterDropAsChildOfExpandedParent(): void {
    $this->expandRow('A');
    $this->drag('B', 'A', 'child');
    $this->assertChildCount(NULL, 4);
    $this->assertChildCount('A', 3);
    $this->assertNoDuplicateRows();
    $this->assertNoBlankRows();
    $this->assertNoVirtualizedRows();
  }

  /**
   * Dropping into a leaf puts exactly one row inside its new group.
   *
   * Catches stray-empty-row regressions from group-creation glitches.
   */
  public function testRowCountsAfterDropAsChildOfLeaf(): void {
    $this->drag('B', 'L', 'child');
    $this->assertChildCount(NULL, 4);
    $this->assertChildCount('L', 1);
    $this->assertNoDuplicateRows();
    $this->assertNoBlankRows();
    $this->assertNoVirtualizedRows();
  }

  /**
   * Dropping into a collapsed parent auto-expands and adds the moved row.
   *
   * D goes from 1 child (D1) to 2 (D1 + B).
   */
  public function testRowCountsAfterDropAsChildOfCollapsedParent(): void {
    $this->drag('B', 'D', 'child');
    $this->assertChildCount(NULL, 4);
    $this->assertChildCount('D', 2);
    $this->assertNoDuplicateRows();
    $this->assertNoBlankRows();
    $this->assertNoVirtualizedRows();
  }

  /**
   * Pulling a deep row up to root demotes the source parent to a leaf.
   *
   * Catches "row stuck in two groups" bugs.
   */
  public function testRowCountsAfterDropChildOutOfParent(): void {
    $this->expandRow('A');
    $this->expandRow('A2');
    $this->drag('A2a', 'L', 'after');
    $this->assertChildCount(NULL, 6);
    $this->assertChildCount('A', 2);
    $this->assertChildCount('A2', 0);
    $this->assertNoDuplicateRows();
    $this->assertNoBlankRows();
    $this->assertNoVirtualizedRows();
  }

  /**
   * Drags the row labelled $source into the $zone of the row labelled $target.
   *
   * Zones map to vertical position inside the target row's rect (the <tr> holds
   * only its own cells, never its subtree, so the row rect IS the header):
   *  - 'before' → 10% from top (top zone)
   *  - 'child'  → 50% (middle zone)
   *  - 'after'  → 90% (bottom zone)
   *
   * The 5px drag threshold in DragController is satisfied by an intermediate
   * pointermove offset before moving to the target.
   */
  private function drag(string $source_label, string $target_label, string $zone): void {
    $source_id = $this->linkIds[$source_label] ?? throw new \InvalidArgumentException("Unknown source label: $source_label");
    $target_id = $this->linkIds[$target_label] ?? throw new \InvalidArgumentException("Unknown target label: $target_label");
    $zone_ratio = match ($zone) {
      'before' => 0.1,
      'child'  => 0.5,
      'after'  => 0.9,
      default  => throw new \InvalidArgumentException("Unknown zone: $zone"),
    };

    $script = <<<JS
      const sourceRow = document.querySelector('tr[role="row"][data-id="$source_id"]');
      const targetRow = document.querySelector('tr[role="row"][data-id="$target_id"]');
      if (!sourceRow || !targetRow) throw new Error('source or target row not in DOM');
      targetRow.scrollIntoView({block: 'center'});
      const handle = sourceRow.querySelector('.menu-tree-ui-row__handle:not(.menu-tree-ui-row__handle--locked)');
      if (!handle) throw new Error('no draggable handle on source row');
      const hRect = handle.getBoundingClientRect();
      const tRect = targetRow.getBoundingClientRect();
      const startX = hRect.left + hRect.width / 2;
      const startY = hRect.top + hRect.height / 2;
      const endX = tRect.left + tRect.width / 2;
      const endY = tRect.top + tRect.height * $zone_ratio;
      function send(t, type, x, y) {
        t.dispatchEvent(new PointerEvent(type, {
          bubbles: true, cancelable: true, composed: true,
          pointerId: 1, isPrimary: true, button: 0,
          clientX: x, clientY: y, screenX: x, screenY: y,
        }));
      }
      send(handle, 'pointerdown', startX, startY);
      send(handle, 'pointermove', startX + 10, startY + 10);
      send(handle, 'pointermove', endX, endY);
      send(handle, 'pointerup', endX, endY);
    JS;

    $this->getSession()->executeScript($script);
    // commitMove flags the <menu-tree> host with data-mtu-moving while the
    // server-first POST + group refresh is in flight; wait for that to clear
    // before assertions run on the rebuilt DOM.
    $this->getSession()->wait(
      5000,
      '!document.querySelector(\'menu-tree[data-mtu-moving="true"]\')'
      . ' && !document.querySelector(\'.menu-tree-ui-row--dragging\')'
      . ' && !document.querySelector(\'[data-loading="true"]\')',
    );
  }

  /**
   * Taps a row's drag handle: pointerdown + pointerup with no movement.
   *
   * Stays below the 5px drag threshold, so it fires the controller's tap path
   * rather than a drag — a pickup when idle, a drop when a move is running.
   */
  private function tapHandle(string $label): void {
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
   * Clicks a destination row in the given zone to aim the active move at it.
   *
   * @param string $label
   *   The destination row label.
   * @param string $zone
   *   Where in the row to click: 'before' (top), 'child' (middle) or 'after'
   *   (bottom).
   */
  private function aimAtRow(string $label, string $zone): void {
    $id = $this->linkIds[$label] ?? throw new \InvalidArgumentException("Unknown label: $label");
    $ratio = match ($zone) {
      'before' => 0.1,
      'child'  => 0.5,
      'after'  => 0.9,
      default  => throw new \InvalidArgumentException("Unknown zone: $zone"),
    };
    $script = <<<JS
      const row = document.querySelector('tr[role="row"][data-id="$id"]');
      if (!row) throw new Error('destination row not in DOM');
      row.scrollIntoView({block: 'center'});
      const rect = row.getBoundingClientRect();
      const x = rect.left + rect.width / 2;
      const y = rect.top + rect.height * $ratio;
      row.dispatchEvent(new MouseEvent('click', {
        bubbles: true, cancelable: true, composed: true,
        clientX: x, clientY: y, button: 0,
      }));
    JS;
    $this->getSession()->executeScript($script);
  }

  /**
   * Expands a row by clicking its toggle button and waits for the open state.
   */
  private function expandRow(string $label): void {
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
   * Returns the DOM row for a labelled link, or NULL if not in DOM.
   */
  private function row(string $label): ?NodeElement {
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
   * Asserts a labelled row's depth (aria-level) matches the expected value.
   */
  private function assertDepth(string $label, int $expected): void {
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
  private function assertIndent(string $label, int $expected_depth): void {
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
  private function assertIsParentRow(string $label): void {
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
    // The toggle must stay in the tab order, including when a move promotes a
    // leaf to a parent (the toggle is then built client-side).
    $this->assertNotSame(
      '-1',
      $toggle->getAttribute('tabindex'),
      "Row $label's toggle must stay keyboard-reachable.",
    );
  }

  /**
   * Asserts a row presents as a leaf (spacer toggle + no has-children).
   */
  private function assertIsLeafRow(string $label): void {
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
   * plugin ID (or '' for the top level). For a leaf — or a former parent demoted
   * to leaf after losing its only child — the expected count must be 0.
   */
  private function assertChildCount(?string $parent_label, int $expected): void {
    if ($parent_label === NULL) {
      $selector = 'menu-tree tbody tr[role="row"][data-parent-id=""]';
      $where = 'top-level';
    }
    else {
      $id = $this->linkIds[$parent_label] ?? throw new \InvalidArgumentException("Unknown label $parent_label");
      $selector = sprintf(
        'menu-tree tbody tr[role="row"][data-parent-id="%s"]',
        $id,
      );
      $where = "under $parent_label";
    }
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
  private function assertNoDuplicateRows(): void {
    $ids = $this->getSession()->evaluateScript(
      'return Array.from(document.querySelectorAll(\'menu-tree tr[role="row"][data-id]\')).map(r => r.dataset.id);',
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
   * fixed height. When the move pipeline re-observes already-visible rows,
   * they can get stuck with that flag set — appearing as empty rows even
   * though they're in the viewport. This catches that.
   *
   * Our fixture is small enough that every row fits in the viewport
   * (Selenium's default is 1280x720+) so there should be no legitimately
   * off-screen rows after a move.
   */
  private function assertNoVirtualizedRows(): void {
    // Wait for the IntersectionObserver to clear the flag on visible rows,
    // rather than guessing a fixed delay that flakes on a loaded runner.
    $this->getSession()->wait(
      2000,
      'Array.from(document.querySelectorAll(\'menu-tree tr[role="row"][data-virtualized="true"]\')).length === 0',
    );
    $virtualized = $this->getSession()->evaluateScript(
      'return Array.from(document.querySelectorAll(\'menu-tree tr[role="row"][data-virtualized="true"]\')).map(r => r.dataset.id);',
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
  private function assertNoBlankRows(): void {
    $blanks = $this->getSession()->evaluateScript(<<<'JS'
      return Array.from(document.querySelectorAll('menu-tree tr[role="row"]'))
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
   * data-parent-id, and document order in the table is the visual order.
   *
   * @param string[] $expected_labels
   *   Labels in the order they should appear as direct children.
   * @param string $row_selector
   *   CSS matching the direct child rows (a `tr[data-parent-id="…"]` set).
   */
  private function assertSiblings(array $expected_labels, string $row_selector): void {
    $rows = $this->getSession()->getPage()->findAll('css', $row_selector);
    $actual = [];
    foreach ($rows as $row) {
      $actual[] = $this->labelFor($row->getAttribute('data-id')) ?? '?';
    }
    $this->assertSame($expected_labels, $actual, "Sibling order matching $row_selector.");
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
  private function childRowsSelector(?string $parent_label): string {
    $parent_id = $parent_label === NULL
      ? ''
      : ($this->linkIds[$parent_label] ?? throw new \InvalidArgumentException("Unknown label $parent_label"));
    return sprintf('tr[role="row"][data-parent-id="%s"]', $parent_id);
  }

  /**
   * Returns the test label for a plugin ID, or NULL if unknown.
   */
  private function labelFor(string $plugin_id): ?string {
    $label = array_search($plugin_id, $this->linkIds, TRUE);
    return $label === FALSE ? NULL : (string) $label;
  }

}
