<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the menu_tree_ui keyboard grab/select move mode end-to-end.
 *
 * Move mode is the keyboard counterpart to pointer drag: Space (or a click on
 * the drag handle) picks up the focused row, Up/Down move it one visual
 * position, Left/Right change the landing depth, and Enter commits through the
 * SAME commitMove() path as drag. The cursor's prospective drop is mirrored on
 * the host as data-treegrid-drop ("kind:id"), which these tests walk to
 * deterministically rather than counting keystrokes.
 *
 * Moves are no longer written to storage on commit; they accumulate in a
 * server-side working copy until saved, so these tests verify each move via
 * the overlaid DOM rather than the persisted entity. Persistence is covered
 * by MenuTreeUiSaveCancelTest.
 *
 * Depth-cap rejection is exercised at the service level in the MenuTreeMover
 * kernel test rather than re-driven here.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiKeyboardMoveTest extends MenuTreeUiJavascriptTestBase {

  use MenuTreeUiKeyboardMoveTrait;

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
   * Drop C between A and B: order becomes A, C, B, D, L.
   *
   * The gap between two siblings is a single stop on the upper one ("after A"),
   * so this navigates to after A rather than a separate "before B".
   */
  public function testGrabAndDropBetweenSiblings(): void {
    $this->moveTo('C', 'after', 'A');
    $this->assertSiblings(['A', 'C', 'B', 'D', 'L'], $this->childRowsSelector(NULL));
    $this->assertDepth('C', 1);
  }

  /**
   * Drop B before the very first item (A): order becomes B, A, C, D, L.
   *
   * The top-of-list "before" stop is the one place a before-box appears.
   */
  public function testGrabAndDropBeforeFirstItem(): void {
    $this->moveTo('B', 'before', 'A');
    $this->assertSiblings(['B', 'A', 'C', 'D', 'L'], $this->childRowsSelector(NULL));
    $this->assertDepth('B', 1);
  }

  /**
   * Drop B after C (sibling): order becomes A, C, B, D, L.
   */
  public function testGrabAndDropAfterSibling(): void {
    $this->moveTo('B', 'after', 'C');
    $this->assertSiblings(['A', 'C', 'B', 'D', 'L'], $this->childRowsSelector(NULL));
    $this->assertDepth('B', 1);
  }

  /**
   * Drop B "on" expanded A: it becomes A's FIRST child (B, A1, A2).
   */
  public function testGrabAndDropAsFirstChildOfExpandedParent(): void {
    $this->expandRow('A');
    $this->moveTo('B', 'child', 'A');
    $this->assertDepth('B', 2);
    $this->assertIndent('B', 2);
    $this->assertSiblings(['B', 'A1', 'A2'], $this->childRowsSelector('A'));
    $this->assertSiblings(['A', 'C', 'D', 'L'], $this->childRowsSelector(NULL));

    $this->assertChildCount(NULL, 4);
    $this->assertChildCount('A', 3);
    $this->assertNoDuplicateRows();
    $this->assertNoBlankRows();
    $this->assertNoVirtualizedRows();
  }

  /**
   * Drop B "on" leaf L: L is promoted to a parent holding B.
   */
  public function testGrabAndDropAsChildOfLeaf(): void {
    $this->moveTo('B', 'child', 'L');
    $this->assertDepth('B', 2);
    $this->assertIndent('B', 2);
    $this->assertIsParentRow('L');
  }

  /**
   * Drop B "on" collapsed D: D auto-expands and B lands inside.
   */
  public function testGrabAndDropAsChildOfCollapsedParentAutoExpands(): void {
    $this->moveTo('B', 'child', 'D');
    $this->assertDepth('B', 2);
    $this->assertSame(
      'true',
      $this->row('D')->getAttribute('aria-expanded'),
      'D should be expanded after a child-drop landed inside it.',
    );
    $children = $this->getSession()->getPage()->findAll(
      'css',
      $this->childRowsSelector('D'),
    );
    $labels = array_map(fn ($el) => $this->labelFor($el->getAttribute('data-id')), $children);
    $this->assertContains('D1', $labels, 'D1 should remain a child of D.');
    $this->assertContains('B', $labels, 'B should appear as a child of D.');
  }

  /**
   * Right opens a collapsed parent mid-move so its children are navigable.
   *
   * Carry B onto D, press Right to open D (revealing D1) before any commit,
   * then position after D1 and drop: D's children become D1, B.
   */
  public function testRightArrowOpensCollapsedParentToPlaceAmongChildren(): void {
    $this->dispatchKeys('B', [' ']);
    $this->stepToDrop('B', 'child:' . $this->linkIds['D']);
    $this->dispatchKeys('B', ['ArrowRight']);

    // Right itself should have opened D, revealing D1 - before any commit.
    $this->assertSession()->waitForElement(
      'css',
      sprintf(
        'tr[role="row"][data-id="%s"][aria-expanded="true"]',
        $this->linkIds['D'],
      ),
    );
    $this->assertSession()->waitForElement(
      'css',
      sprintf('tr[role="row"][data-parent-id="%s"]', $this->linkIds['D']),
    );
    $this->assertSame(
      'true',
      $this->row('D')->getAttribute('aria-expanded'),
      'Right should open the collapsed parent during the move, not only on drop.',
    );

    // Place after D1 and drop.
    $this->stepToDrop('B', 'after:' . $this->linkIds['D1']);
    $this->dispatchKeys('B', ['Enter']);
    $this->settle();

    $this->assertSiblings(['D1', 'B'], $this->childRowsSelector('D'));
  }

  /**
   * Walk a deep child up to the top level, demoting its former parent.
   */
  public function testGrabChildOutOfParent(): void {
    $this->expandRow('A');
    $this->expandRow('A2');
    $this->moveTo('A2a', 'after', 'L');
    $this->assertDepth('A2a', 1);
    $this->assertSiblings(['A', 'B', 'C', 'D', 'L', 'A2a'], $this->childRowsSelector(NULL));
    $this->assertIsLeafRow('A2');
  }

  /**
   * Space enters move mode (grabbed class on); Escape leaves it cleanly.
   */
  public function testGrabEntersAndEscapeExitsMoveMode(): void {
    $this->dispatchKeys('B', [' ']);
    $this->assertTrue(
      $this->row('B')->hasClass('menu-tree-ui-row--grabbed'),
      'B should carry the grabbed class once picked up.',
    );

    $this->dispatchKeys('B', ['Escape']);
    $this->assertFalse(
      $this->row('B')->hasClass('menu-tree-ui-row--grabbed'),
      'B should drop the grabbed class after Escape.',
    );
  }

  /**
   * Escape after navigating leaves the tree and persistence unchanged.
   */
  public function testEscapeCancelsMove(): void {
    $this->dispatchKeys('B', [' ', 'ArrowDown', 'ArrowDown', 'Escape']);
    $this->assertSiblings(['A', 'B', 'C', 'D', 'L'], $this->childRowsSelector(NULL));
    $this->assertSame(
      $this->linkIds['B'],
      $this->getSession()->evaluateScript('return document.activeElement?.dataset.id;'),
      'Focus should return to the grabbed row after cancelling.',
    );
  }

  /**
   * Dropping a row back into its own slot is a no-op (no server round-trip).
   *
   * The grab starts the cursor on the row's current slot, so pressing Enter
   * immediately should leave the order untouched.
   */
  public function testNoOpDropLeavesOrderUnchanged(): void {
    $this->dispatchKeys('B', [' ', 'Enter']);
    $this->settle();
    $this->assertSiblings(['A', 'B', 'C', 'D', 'L'], $this->childRowsSelector(NULL));
  }

  /**
   * A click on a row while grabbed sets the drop cursor to the clicked zone.
   *
   * Mouse and keyboard combine: after picking B up with the keyboard, clicking
   * the middle of leaf L aims the cursor at "child of L", and Enter drops there.
   */
  public function testClickSetsDropCursorThenDrops(): void {
    $this->dispatchKeys('B', [' ']);
    $this->clickRowZone('L', 0.5);

    $this->assertSame(
      'child:' . $this->linkIds['L'],
      $this->readDrop(),
      'Clicking the middle of L should aim the cursor at child-of-L.',
    );

    $this->dispatchKeys('B', ['Enter']);
    $this->settle();
    $this->assertIsParentRow('L');
    $this->assertSiblings(['B'], $this->childRowsSelector('L'));
  }

  /**
   * Focusing the search field does not cancel an in-progress move.
   *
   * The editor can search for a destination mid-move; the grab must stick (the
   * move is cancelled only by Escape, a drop, or a click outside the UI).
   */
  public function testMoveSurvivesSearchFieldFocus(): void {
    $this->dispatchKeys('B', [' ']);
    $this->assertTrue(
      $this->row('B')->hasClass('menu-tree-ui-row--grabbed'),
      'B should be grabbed.',
    );

    $this->getSession()->getPage()->find('css', '#menu-tree-ui-search-input')->click();

    $this->assertTrue(
      $this->row('B')->hasClass('menu-tree-ui-row--grabbed'),
      'The move should survive focusing the search field.',
    );
  }

  /**
   * Down/Up move a held row one visual position; Left/Right change its depth.
   *
   * Grabs A1 (first child of expanded A) and walks the cursor with the new
   * orthogonal controls: one Down lands it as a sibling after A2 (still depth
   * 2), then Left pulls it out to the top level after A. This is the model the
   * "up/down move one position, left/right change depth" request describes.
   */
  public function testUpDownMoveOnePositionLeftRightChangeDepth(): void {
    $this->expandRow('A');
    $this->dispatchKeys('A1', [' ']);

    // Grab starts on A1's own slot (before A2): after:A as A's first child.
    $this->stepToDrop('A1', 'child:' . $this->linkIds['A']);
    $this->assertDepth('A1', 2);

    // One Down moves it one position: now after A2, still a child of A.
    $this->dispatchKeys('A1', ['ArrowDown']);
    $this->assertSame(
      'after:' . $this->linkIds['A2'],
      $this->readDrop(),
      'Down should move the held row one position, to after A2.',
    );
    $this->assertDepth('A1', 2);

    // Left changes only the depth: out of A, to the top level after A.
    $this->dispatchKeys('A1', ['ArrowLeft']);
    $this->assertSame(
      'after:' . $this->linkIds['A'],
      $this->readDrop(),
      'Left should outdent the held row to the top level without moving it.',
    );
    $this->assertDepth('A1', 1);

    $this->dispatchKeys('A1', ['Enter']);
    $this->settle();
    $this->assertSiblings(['A', 'A1', 'B', 'C', 'D', 'L'], $this->childRowsSelector(NULL));
  }

  /**
   * The held row stays visible and no key closes the section it sits in.
   *
   * Regression guard for the "cursor gets lost when the section closes"
   * report: carry B inside expanded A, then press Left. The old Left collapsed
   * the highlighted item, which hid the carried row; now A stays open and B
   * stays visible.
   */
  public function testHeldRowStaysVisibleAndKeepsItsSectionOpen(): void {
    $this->expandRow('A');
    $this->dispatchKeys('B', [' ']);
    $this->stepToDrop('B', 'child:' . $this->linkIds['A']);

    $this->assertSame(
      'true',
      $this->row('A')->getAttribute('aria-expanded'),
      'A should be open while B is carried inside it.',
    );
    $this->assertHeldRowVisible('B');

    // The held row is wedged between A and A1, so its depth is pinned; Left
    // must leave the section open rather than collapse it and lose the row.
    $this->dispatchKeys('B', ['ArrowLeft']);
    $this->assertSame(
      'true',
      $this->row('A')->getAttribute('aria-expanded'),
      'Left must not close the section the held row is displayed in.',
    );
    $this->assertHeldRowVisible('B');

    // Clicking A's collapse toggle mid-move must also leave it open: folding it
    // would hide the carried row and lose the cursor.
    $this->row('A')
      ->find('css', '.menu-tree-ui-row__name button.menu-tree-ui-row__toggle')
      ->click();
    $this->assertSame(
      'true',
      $this->row('A')->getAttribute('aria-expanded'),
      'Clicking the toggle must not collapse a section holding the carried row.',
    );
    $this->assertHeldRowVisible('B');
  }

  /**
   * A click on the drag handle picks the row up into keyboard move mode.
   *
   * The mouse entry point the request asks for: click to pick up, then drive
   * with the keyboard. A plain tap (no drag threshold crossed) enters move
   * mode rather than starting a drag.
   */
  public function testClickHandlePicksUpRowForMoveMode(): void {
    $this->tapHandle('B');
    $grabbed = $this->assertSession()->waitForElement(
      'css',
      sprintf(
        'tr[role="row"][data-id="%s"].menu-tree-ui-row--grabbed',
        $this->linkIds['B'],
      ),
    );
    $this->assertNotNull(
      $grabbed,
      'Clicking the handle should pick the row up into move mode.',
    );

    // The keyboard then drives the move: drop B after C and confirm with Enter.
    $this->stepToDrop('B', 'after:' . $this->linkIds['C']);
    $this->dispatchKeys('B', ['Enter']);
    $this->settle();
    $this->assertSiblings(['A', 'C', 'B', 'D', 'L'], $this->childRowsSelector(NULL));
  }

}
