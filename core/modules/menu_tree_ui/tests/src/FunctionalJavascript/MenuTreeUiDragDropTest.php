<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the menu_tree_ui drag-and-drop behavior end-to-end.
 *
 * Each scenario fires synthetic pointer events at the real DragController
 * pipeline, then verifies the gesture landed in the aimed slot and the DOM
 * reconciled around it: the moved row's `aria-level` matches the destination
 * level, it sits next to the row it was aimed at, and the affected groups'
 * row counts settle with no duplicate, blank, or virtualized rows left
 * behind. Exact sibling order is owned by the kernel overlay/mover tests and
 * the spliceBefore unit table, not re-asserted here.
 *
 * A drop records the move in a server-side working copy until saved, so
 * these tests assert the overlaid tree rather than the persisted entity.
 * Persistence is covered by MenuTreeUiSaveCancelTest.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiDragDropTest extends MenuTreeUiJavascriptTestBase {

  use MenuTreeUiDragGestureTrait;

  /**
   * Seeds a known menu tree.
   *
   * Top level: A (parent), B (leaf), C (leaf), D (parent), L (leaf).
   * A children: A1 (leaf), A2 (parent of A2a).
   * D children: D1 (leaf). D stays collapsed by default.
   */
  protected function seedMenu(): void {
    $this->createTestMenu();

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
   * Drop C in the top zone of B → C lands immediately before B.
   *
   * Also checks the top level reconciles to 5 rows with no duplicate, blank,
   * or virtualized rows left behind by the refresh.
   */
  public function testDropBeforeSibling(): void {
    $this->drag('C', 'B', 'before');
    $this->assertAdjacent('C', 'B', $this->childRowsSelector(NULL));
    $this->assertDepth('C', 1);
    $this->assertIndent('C', 1);
    $this->assertChildCount(NULL, 5);
    $this->assertNoDuplicateRows();
    $this->assertNoBlankRows();
    $this->assertNoVirtualizedRows();
  }

  /**
   * Drop B in the bottom zone of C → C lands immediately before B.
   */
  public function testDropAfterSibling(): void {
    $this->drag('B', 'C', 'after');
    $this->assertAdjacent('C', 'B', $this->childRowsSelector(NULL));
    $this->assertDepth('B', 1);
    $this->assertIndent('B', 1);
  }

  /**
   * A pointer-only move commits on a handle tap, with no keyboard step.
   *
   * Exercises the single-pointer path WCAG 2.5.7 requires: tapping C's handle
   * picks it up, clicking the top of B aims it before B, and tapping C's handle
   * again drops it. C lands immediately before B without ever pressing Enter.
   */
  public function testPointerOnlyMoveCommitsOnHandleTap(): void {
    $this->tapHandle('C');
    $this->aimAtRow('B', 'before');
    $this->tapHandle('C');
    $this->getSession()->wait(
      5000,
      '!document.querySelector(\'drupal-treegrid[data-treegrid-reorder-moving="true"]\')'
      . ' && !document.querySelector(\'.menu-tree-ui-row--grabbed\')'
      . ' && !document.querySelector(\'[data-treegrid-loading]\')',
    );
    $this->assertAdjacent('C', 'B', $this->childRowsSelector(NULL));
    $this->assertDepth('C', 1);
  }

  /**
   * Drop B in the middle of expanded A → B becomes A's first child at level 2.
   *
   * Dropping "on" a parent lands the row as the first child (right under the
   * parent header, where the drop is aimed), not the last. Also checks the
   * groups reconcile to 4 top-level rows and 3 children of A, with no
   * duplicate, blank, or virtualized rows left behind.
   */
  public function testDropAsChildOfExpandedParent(): void {
    $this->expandRow('A');
    $this->drag('B', 'A', 'child');
    $this->assertDepth('B', 2);
    $this->assertIndent('B', 2);
    $this->assertSiblings(['B', 'A1', 'A2'], $this->childRowsSelector('A'));
    $this->assertChildCount(NULL, 4);
    $this->assertChildCount('A', 3);
    $this->assertNoDuplicateRows();
    $this->assertNoBlankRows();
    $this->assertNoVirtualizedRows();
  }

  /**
   * Drop B in the middle of leaf L → L is promoted to a parent containing B.
   *
   * The leaf should pick up a real toggle button so the new subtree can be
   * collapsed/expanded after the drop — not a placeholder spacer span. Also
   * checks the groups reconcile to 4 top-level rows and 1 child of L, with no
   * duplicate, blank, or virtualized rows left behind.
   */
  public function testDropAsChildOfLeaf(): void {
    $this->drag('B', 'L', 'child');
    $this->assertDepth('B', 2);
    $this->assertIndent('B', 2);
    $this->assertIsParentRow('L');
    $this->assertChildCount(NULL, 4);
    $this->assertChildCount('L', 1);
    $this->assertNoDuplicateRows();
    $this->assertNoBlankRows();
    $this->assertNoVirtualizedRows();
  }

  /**
   * Drop B in the middle of collapsed D → D auto-expands, B becomes its child.
   *
   * Also checks the groups reconcile to 4 top-level rows and 2 children of D
   * (the pre-existing D1 plus the moved B), with no duplicate, blank, or
   * virtualized rows left behind.
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
    $this->assertChildCount(NULL, 4);
    $this->assertChildCount('D', 2);
    $this->assertNoDuplicateRows();
    $this->assertNoBlankRows();
    $this->assertNoVirtualizedRows();
  }

  /**
   * Dragging a child out of its parent: A2a lifted to top level (after L).
   *
   * After A2a is moved, A2 has no remaining children. The UI should reflect
   * that — `data-has-children` flipping to "false", `aria-expanded` cleared,
   * and the toggle button swapped back to a spacer — otherwise the chevron
   * stays clickable on an empty subtree. Also checks the groups reconcile to
   * 6 top-level rows, 2 remaining children of A, and 0 children of A2, with
   * no duplicate, blank, or virtualized rows left behind.
   */
  public function testDropChildOutOfParent(): void {
    $this->expandRow('A');
    $this->expandRow('A2');
    $this->drag('A2a', 'L', 'after');

    $this->assertDepth('A2a', 1);
    $this->assertIndent('A2a', 1);
    $this->assertAdjacent('L', 'A2a', $this->childRowsSelector(NULL));

    $this->assertIsLeafRow('A2');
    $this->assertChildCount(NULL, 6);
    $this->assertChildCount('A', 2);
    $this->assertChildCount('A2', 0);
    $this->assertNoDuplicateRows();
    $this->assertNoBlankRows();
    $this->assertNoVirtualizedRows();
  }

  /**
   * Hovering over a collapsed parent for one second mid-drag auto-expands it.
   *
   * No pointerup is fired — we only assert the parent opened. The drag is
   * then canceled so subsequent tests aren't polluted.
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

}
