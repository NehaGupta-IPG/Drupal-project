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
 * Tests the menu_tree_ui keyboard grab/select move mode end-to-end.
 *
 * Move mode is the keyboard counterpart to pointer drag: Space (or a click on
 * the drag handle) picks up the focused row, Up/Down move it one visual
 * position, Left/Right change the landing depth, and Enter commits through the
 * SAME commitMove() path as drag. The cursor's prospective drop is mirrored on
 * the host as data-mtu-drop ("kind:id"), which these tests walk to
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
final class MenuTreeUiKeyboardMoveTest extends WebDriverTestBase {

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
    $this->assertSession()->waitForElement(
      'css',
      'drupal-treegrid table[role="treegrid"] tbody tr[role="row"]'
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

  /**
   * Fires a tap (pointerdown + pointerup, no drag) on a row's handle.
   *
   * Mirrors the synthetic pointer events the drag test uses, but without the
   * intervening pointermove, so the DragController treats it as a click rather
   * than a drag and routes it to move-mode pickup.
   *
   * @param string $label
   *   The row label whose handle to tap.
   */
  private function tapHandle(string $label): void {
    $id = $this->linkIds[$label];
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
   * Asserts a held row is in the DOM, not hidden, and laid out (visible).
   *
   * @param string $label
   *   The held row's label.
   */
  private function assertHeldRowVisible(string $label): void {
    $id = $this->linkIds[$label];
    $script = <<<JS
      return (function () {
        const row = document.querySelector('tr[role="row"][data-id="$id"]');
        if (!row) return 'missing';
        if (row.dataset.hidden === 'true') return 'data-hidden';
        const visible = typeof row.checkVisibility === 'function'
          ? row.checkVisibility()
          : row.offsetParent !== null;
        return visible ? 'visible' : 'not-visible';
      })();
    JS;
    $this->assertSame(
      'visible',
      (string) $this->getSession()->evaluateScript($script),
      "Held row $label should remain visible.",
    );
  }

  /**
   * Dispatches a click on a row at a vertical zone (0 = top, 1 = bottom).
   *
   * The click lands on the title so it never hits a row control, and carries a
   * clientY in the chosen zone so the move-mode intent (before/child/after) is
   * computed from it.
   *
   * @param string $label
   *   The row label to click.
   * @param float $ratio
   *   The vertical position within the row, 0..1.
   */
  private function clickRowZone(string $label, float $ratio): void {
    $id = $this->linkIds[$label];
    $script = <<<JS
      const row = document.querySelector('tr[role="row"][data-id="$id"]');
      const rect = row.getBoundingClientRect();
      const x = rect.left + rect.width * 0.5;
      const y = rect.top + rect.height * $ratio;
      const hit = row.querySelector('.menu-tree-ui-row__title') || row;
      hit.dispatchEvent(new MouseEvent('click', {
        bubbles: true, cancelable: true, clientX: x, clientY: y,
      }));
    JS;
    $this->getSession()->executeScript($script);
  }

  /**
   * Grabs $source, walks the cursor to "$kind:$target", drops, and settles.
   */
  private function moveTo(string $source, string $kind, string $target_label): void {
    $this->dispatchKeys($source, [' ']);
    $this->stepToDrop($source, $kind . ':' . $this->linkIds[$target_label]);
    $this->dispatchKeys($source, ['Enter']);
    $this->settle();
  }

  /**
   * Walks the move cursor to a wanted "kind:id" drop position.
   *
   * The cursor is two-dimensional now: Up/Down move it one visual position and
   * Left/Right change the landing depth. This parks at the top, then for each
   * gap sweeps depth from shallowest (Left) to deepest (Right), checking the
   * host's data-mtu-drop after each press - deterministic regardless of where
   * the grab began. Right may open a collapsed parent (the documented
   * behavior), which only adds reachable positions.
   *
   * @param string $source
   *   Label of the grabbed row (the node keydowns are dispatched on).
   * @param string $want
   *   The wanted data-mtu-drop value, "kind:pluginId".
   * @param int $max
   *   Safety bound on the number of gaps / depths swept.
   */
  private function stepToDrop(string $source, string $want, int $max = 40): void {
    $this->dispatchKeys($source, array_fill(0, $max, 'ArrowUp'));
    for ($gap = 0; $gap <= $max; $gap++) {
      $this->dispatchKeys($source, array_fill(0, $max, 'ArrowLeft'));
      for ($depth = 0; $depth <= $max; $depth++) {
        if ($this->readDrop() === $want) {
          return;
        }
        $before = $this->readDrop();
        $this->dispatchKeys($source, ['ArrowRight']);
        if ($this->readDrop() === $before) {
          break;
        }
      }
      $this->dispatchKeys($source, ['ArrowDown']);
    }
    $this->fail(sprintf(
      "Move cursor never reached '%s'; last was %s.",
      $want,
      var_export($this->readDrop(), TRUE),
    ));
  }

  /**
   * Reads the host's current prospective drop ("kind:id"), or NULL.
   */
  private function readDrop(): ?string {
    return $this->getSession()->evaluateScript(
      "return document.querySelector('drupal-treegrid').getAttribute('data-mtu-drop');",
    );
  }

  /**
   * Focuses the source row and dispatches a sequence of keydown events on it.
   *
   * The keydowns bubble to the tree, where the <drupal-treegrid> element routes them
   * to the MoveModeController. Focus stays on the grabbed row throughout move
   * mode, so the whole sequence targets one node.
   *
   * @param string $source_label
   *   The label of the row to act on.
   * @param string[] $keys
   *   KeyboardEvent.key values to dispatch in order (e.g. ' ', 'ArrowDown').
   */
  private function dispatchKeys(string $source_label, array $keys): void {
    $source_id = $this->linkIds[$source_label] ?? throw new \InvalidArgumentException("Unknown source label: $source_label");
    $keys_json = json_encode(array_values($keys));

    $script = <<<JS
      const source = document.querySelector('tr[role="row"][data-id="$source_id"]');
      if (!source) throw new Error('source row not in DOM');
      source.scrollIntoView({block: 'center'});
      source.tabIndex = 0;
      source.focus();
      for (const k of $keys_json) {
        source.dispatchEvent(new KeyboardEvent('keydown', {
          key: k, bubbles: true, cancelable: true,
        }));
      }
    JS;

    $this->getSession()->executeScript($script);
  }

  /**
   * Waits until any committed move's async refresh work has settled.
   */
  private function settle(): void {
    $this->getSession()->wait(
      5000,
      '!document.querySelector(\'drupal-treegrid[data-mtu-moving="true"]\')'
      . ' && !document.querySelector(\'.menu-tree-ui-row--grabbed\')'
      . ' && !document.querySelector(\'[data-loading="true"]\')',
    );
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
   * plugin ID (or '' for the top level). Pass NULL for the top level.
   */
  private function assertChildCount(?string $parent_label, int $expected): void {
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
   */
  private function assertNoDuplicateRows(): void {
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
   */
  private function assertNoVirtualizedRows(): void {
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
      'Rows stuck marked data-virtualized="true" after the move: ' . json_encode($virtualized),
    );
  }

  /**
   * Asserts every row in DOM has its title element populated.
   */
  private function assertNoBlankRows(): void {
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
