<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the menu_tree_ui drag-and-drop behaviour end-to-end.
 *
 * Each scenario fires synthetic pointer events at the real DragController
 * pipeline, then verifies three layers:
 *  - DOM: the moved row's `aria-level` matches the destination level.
 *  - DOM: sibling order inside the destination container is correct.
 *  - Persistence: the underlying menu_link_content entity's `parent` field
 *    is set to the expected plugin ID (or '' for root).
 *
 * @group menu_tree_ui
 */
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
    $this->drupalGet('/admin/structure/menu-tree-ui/test');
    // Wait until the <menu-tree> custom element has at least one top-level
    // row in DOM — that's the "page upgraded" signal.
    $this->assertSession()->waitForElement(
      'css',
      'menu-tree ul[role="tree"] > li[role="treeitem"]'
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
    $this->assertSiblings(['A', 'C', 'B', 'D', 'L'], 'ul[role="tree"]');
    $this->assertDepth('C', 1);
    $this->assertIndent('C', 1);
    $this->assertPersistedParent('C', NULL);
  }

  /**
   * Drop B in the bottom zone of C → order becomes A, C, B, D, L.
   */
  public function testDropAfterSibling(): void {
    $this->drag('B', 'C', 'after');
    $this->assertSiblings(['A', 'C', 'B', 'D', 'L'], 'ul[role="tree"]');
    $this->assertDepth('B', 1);
    $this->assertIndent('B', 1);
    $this->assertPersistedParent('B', NULL);
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
    $this->assertPersistedParent('B', 'A');
    $this->assertSiblings(
      ['B', 'A1', 'A2'],
      sprintf('li[data-id="%s"] > ul[role="group"]', $this->linkIds['A']),
    );
    // Top level should now exclude B.
    $this->assertSiblings(['A', 'C', 'D', 'L'], 'ul[role="tree"]');
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
    $this->assertPersistedParent('B', 'L');
    $this->assertIsParentRow('L');
  }

  /**
   * Drop B in the middle of collapsed D → D auto-expands, B becomes its child.
   */
  public function testDropAsChildOfCollapsedParentAutoExpands(): void {
    $this->drag('B', 'D', 'child');
    $this->assertDepth('B', 2);
    $this->assertIndent('B', 2);
    $this->assertPersistedParent('B', 'D');
    $this->assertSame(
      'true',
      $this->row('D')->getAttribute('aria-expanded'),
      'D should be expanded after a child-drop landed inside it.',
    );
    // D1 (pre-existing child) should still be inside D alongside the moved B.
    $children = $this->getSession()->getPage()->findAll(
      'css',
      sprintf('li[data-id="%s"] > ul[role="group"] > li[role="treeitem"]', $this->linkIds['D']),
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
    $this->assertPersistedParent('A2a', NULL);
    $this->assertSiblings(['A', 'B', 'C', 'D', 'L', 'A2a'], 'ul[role="tree"]');

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
      const source = document.querySelector('li[role="treeitem"][data-id="$source_id"]');
      const target = document.querySelector('li[role="treeitem"][data-id="$target_id"]');
      target.scrollIntoView({block: 'center'});
      const handle = source.querySelector('.menu-tree-ui-row__handle:not(.menu-tree-ui-row__handle--locked)');
      const hRect = handle.getBoundingClientRect();
      const tRect = target.querySelector(':scope > .menu-tree-ui-row__content').getBoundingClientRect();
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
      sprintf('li[role="treeitem"][data-id="%s"][aria-expanded="true"]', $target_id),
      1500,
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
    $this->assertSiblings(['A', 'B', 'C', 'D', 'L'], 'ul[role="tree"]');
    $this->assertPersistedParent('A', NULL);
  }

  /**
   * Dropping a row onto its own descendant is blocked client-side.
   */
  public function testCannotDropOntoDescendant(): void {
    $this->expandRow('A');
    $this->drag('A', 'A1', 'after');
    $this->assertSiblings(['A', 'B', 'C', 'D', 'L'], 'ul[role="tree"]');
    $this->assertPersistedParent('A1', 'A');
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
    $this->assertNoVirtualisedRows();
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
    $this->assertNoVirtualisedRows();
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
    $this->assertNoVirtualisedRows();
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
    $this->assertNoVirtualisedRows();
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
    $this->assertNoVirtualisedRows();
  }

  /**
   * Drags the row labelled $source into the $zone of the row labelled $target.
   *
   * Zones map to vertical position inside the target's header (the row's
   * .menu-tree-ui-row__content rect, not the whole li including subtree):
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
      const sourceRow = document.querySelector('li[role="treeitem"][data-id="$source_id"]');
      const targetRow = document.querySelector('li[role="treeitem"][data-id="$target_id"]');
      if (!sourceRow || !targetRow) throw new Error('source or target row not in DOM');
      targetRow.scrollIntoView({block: 'center'});
      const handle = sourceRow.querySelector('.menu-tree-ui-row__handle:not(.menu-tree-ui-row__handle--locked)');
      if (!handle) throw new Error('no draggable handle on source row');
      const hRect = handle.getBoundingClientRect();
      const tHeader = targetRow.querySelector(':scope > .menu-tree-ui-row__content');
      const tRect = tHeader.getBoundingClientRect();
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
   * Expands a row by clicking its toggle button and waits for the open state.
   */
  private function expandRow(string $label): void {
    $row = $this->row($label);
    $this->assertNotNull($row, "Row $label not in DOM.");
    $toggle = $row->find('css', ':scope > .menu-tree-ui-row__content button.menu-tree-ui-row__toggle');
    $this->assertNotNull($toggle, "Row $label has no toggle button.");
    $toggle->click();
    $this->assertSession()->waitForElement(
      'css',
      sprintf('li[role="treeitem"][data-id="%s"][aria-expanded="true"] > ul[role="group"] > li', $this->linkIds[$label]),
    );
  }

  /**
   * Returns the DOM row for a labelled link, or NULL if not in DOM.
   */
  private function row(string $label) {
    $id = $this->linkIds[$label] ?? NULL;
    if ($id === NULL) {
      return NULL;
    }
    return $this->getSession()->getPage()->find(
      'css',
      sprintf('li[role="treeitem"][data-id="%s"]', $id),
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
   * regardless of how the inline rem value is serialised.
   */
  private function assertIndent(string $label, int $expected_depth): void {
    $id = $this->linkIds[$label] ?? NULL;
    $this->assertNotNull($id, "Unknown label $label.");
    $script = sprintf(
      "return window.getComputedStyle(document.querySelector('li[data-id=\"%s\"] > .menu-tree-ui-row__content')).paddingInlineStart;",
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
      ':scope > .menu-tree-ui-row__content > button.menu-tree-ui-row__toggle',
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
        ':scope > .menu-tree-ui-row__content > button.menu-tree-ui-row__toggle',
      ),
      "Row $label should no longer have a <button> toggle.",
    );
    $spacer = $row->find(
      'css',
      ':scope > .menu-tree-ui-row__content > span.menu-tree-ui-row__toggle--spacer',
    );
    $this->assertNotNull(
      $spacer,
      "Row $label should have a spacer in its toggle slot.",
    );
  }

  /**
   * Asserts the persisted parent of a labelled link matches expectations.
   *
   * NULL means "should be at root" (parent = '').
   */
  private function assertPersistedParent(string $label, ?string $expected_parent_label): void {
    $entity = $this->reloadEntity($label);
    $expected = $expected_parent_label === NULL
      ? ''
      : $this->linkIds[$expected_parent_label];
    $this->assertSame(
      $expected,
      $entity->getParentId(),
      "Persisted parent of $label.",
    );
  }

  /**
   * Asserts the number of direct `<li role="treeitem">` children of a parent.
   *
   * Pass NULL for the parent label to count direct top-level rows under
   * `<ul role="tree">`. For a row that has no `<ul role="group">` in DOM
   * (a leaf — or a former parent demoted to leaf after losing its only
   * child) the expected count must be 0.
   */
  private function assertChildCount(?string $parent_label, int $expected): void {
    if ($parent_label === NULL) {
      $selector = 'menu-tree > ul[role="tree"] > li[role="treeitem"]';
      $where = 'top-level';
    }
    else {
      $id = $this->linkIds[$parent_label] ?? throw new \InvalidArgumentException("Unknown label $parent_label");
      $selector = sprintf(
        'li[data-id="%s"] > ul[role="group"] > li[role="treeitem"]',
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
   * Catches refresh / move bugs that leave a row in its source group while
   * also adding it to the destination.
   */
  private function assertNoDuplicateRows(): void {
    $ids = $this->getSession()->evaluateScript(
      'return Array.from(document.querySelectorAll(\'menu-tree li[role="treeitem"][data-id]\')).map(r => r.dataset.id);',
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
   * Asserts no row currently in the test viewport is marked virtualised.
   *
   * The IntersectionObserver virtualisation in windowing.js sets
   * `data-virtualised="true"` to hide content of off-screen rows and reserve
   * fixed height. When the move pipeline re-observes already-visible rows,
   * they can get stuck with that flag set — appearing as empty rows even
   * though they're in the viewport. This catches that.
   *
   * Our fixture is small enough that every row fits in the viewport
   * (Selenium's default is 1280x720+) so there should be no legitimately
   * off-screen rows after a move.
   */
  private function assertNoVirtualisedRows(): void {
    // Give the IntersectionObserver a frame to deliver its initial entries.
    $this->getSession()->wait(150);
    $virtualised = $this->getSession()->evaluateScript(
      'return Array.from(document.querySelectorAll(\'menu-tree li[role="treeitem"][data-virtualised="true"]\')).map(r => r.dataset.id);',
    );
    $this->assertSame(
      [],
      $virtualised,
      'Rows stuck marked data-virtualised="true" after the move (will render as empty): '
      . json_encode($virtualised),
    );
  }

  /**
   * Asserts every row in DOM has its title element populated.
   *
   * Catches "empty row" regressions: a row whose `__content` was wiped
   * or never built, leaving an `<li>` shell with no visible label.
   */
  private function assertNoBlankRows(): void {
    $blanks = $this->getSession()->evaluateScript(<<<'JS'
      return Array.from(document.querySelectorAll('menu-tree li[role="treeitem"]'))
        .filter(r => {
          const t = r.querySelector(':scope > .menu-tree-ui-row__content > .menu-tree-ui-row__title');
          return !t || !t.textContent || t.textContent.trim() === '';
        })
        .map(r => r.dataset.id || '(no data-id)');
    JS
    );
    $this->assertSame([], $blanks, 'Found blank rows: ' . json_encode($blanks));
  }

  /**
   * Asserts the ordered DOM siblings inside a container.
   *
   * @param string[] $expected_labels
   *   Labels in the order they should appear as direct children of the
   *   matched container.
   * @param string $container_selector
   *   CSS for the parent UL.
   */
  private function assertSiblings(array $expected_labels, string $container_selector): void {
    $rows = $this->getSession()->getPage()->findAll(
      'css',
      $container_selector . ' > li[role="treeitem"]',
    );
    $actual = [];
    foreach ($rows as $row) {
      $actual[] = $this->labelFor($row->getAttribute('data-id')) ?? '?';
    }
    $this->assertSame($expected_labels, $actual, "Sibling order under $container_selector.");
  }

  /**
   * Returns the test label for a plugin ID, or NULL if unknown.
   */
  private function labelFor(string $plugin_id): ?string {
    $label = array_search($plugin_id, $this->linkIds, TRUE);
    return $label === FALSE ? NULL : (string) $label;
  }

  /**
   * Reloads a labelled link entity fresh from storage (no cache).
   */
  private function reloadEntity(string $label): MenuLinkContentInterface {
    $plugin_id = $this->linkIds[$label];
    $uuid = substr($plugin_id, strlen('menu_link_content:'));
    $storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
    $storage->resetCache();
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uuid', $uuid)
      ->execute();
    $this->assertNotEmpty($ids, "Entity for $label not found in storage.");
    /** @var \Drupal\menu_link_content\MenuLinkContentInterface $entity */
    $entity = $storage->load(reset($ids));
    return $entity;
  }

}
