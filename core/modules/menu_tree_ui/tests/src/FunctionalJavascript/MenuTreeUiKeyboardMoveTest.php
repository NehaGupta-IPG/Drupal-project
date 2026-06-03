<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the menu_tree_ui keyboard grab/select move mode end-to-end.
 *
 * Move mode is the keyboard counterpart to pointer drag: Space picks up the
 * focused row, the arrow keys move a box-style drop position through the tree,
 * and Enter commits through the SAME commitMove() path as drag. Each stop
 * references a row and is mirrored on the host as data-mtu-drop ("kind:id"),
 * which these tests walk to deterministically rather than counting keystrokes.
 *
 * Depth-cap rejection is exercised at the service level in the MenuTreeMover
 * kernel test rather than re-driven here.
 *
 * @group menu_tree_ui
 */
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
    $this->drupalGet('/admin/structure/menu-tree-ui/test');
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
   * Drop C between A and B: order becomes A, C, B, D, L.
   *
   * The gap between two siblings is a single stop on the upper one ("after A"),
   * so this navigates to after A rather than a separate "before B".
   */
  public function testGrabAndDropBetweenSiblings(): void {
    $this->moveTo('C', 'after', 'A');
    $this->assertSiblings(['A', 'C', 'B', 'D', 'L'], 'ul[role="tree"]');
    $this->assertDepth('C', 1);
    $this->assertPersistedParent('C', NULL);
  }

  /**
   * Drop B before the very first item (A): order becomes B, A, C, D, L.
   *
   * The top-of-list "before" stop is the one place a before-box appears.
   */
  public function testGrabAndDropBeforeFirstItem(): void {
    $this->moveTo('B', 'before', 'A');
    $this->assertSiblings(['B', 'A', 'C', 'D', 'L'], 'ul[role="tree"]');
    $this->assertDepth('B', 1);
    $this->assertPersistedParent('B', NULL);
  }

  /**
   * Drop B after C (sibling): order becomes A, C, B, D, L.
   */
  public function testGrabAndDropAfterSibling(): void {
    $this->moveTo('B', 'after', 'C');
    $this->assertSiblings(['A', 'C', 'B', 'D', 'L'], 'ul[role="tree"]');
    $this->assertDepth('B', 1);
    $this->assertPersistedParent('B', NULL);
  }

  /**
   * Drop B "on" expanded A: it becomes A's FIRST child (B, A1, A2).
   */
  public function testGrabAndDropAsFirstChildOfExpandedParent(): void {
    $this->expandRow('A');
    $this->moveTo('B', 'child', 'A');
    $this->assertDepth('B', 2);
    $this->assertIndent('B', 2);
    $this->assertPersistedParent('B', 'A');
    $this->assertSiblings(
      ['B', 'A1', 'A2'],
      sprintf('li[data-id="%s"] > ul[role="group"]', $this->linkIds['A']),
    );
    $this->assertSiblings(['A', 'C', 'D', 'L'], 'ul[role="tree"]');

    $this->assertChildCount(NULL, 4);
    $this->assertChildCount('A', 3);
    $this->assertNoDuplicateRows();
    $this->assertNoBlankRows();
    $this->assertNoVirtualisedRows();
  }

  /**
   * Drop B "on" leaf L: L is promoted to a parent holding B.
   */
  public function testGrabAndDropAsChildOfLeaf(): void {
    $this->moveTo('B', 'child', 'L');
    $this->assertDepth('B', 2);
    $this->assertIndent('B', 2);
    $this->assertPersistedParent('B', 'L');
    $this->assertIsParentRow('L');
  }

  /**
   * Drop B "on" collapsed D: D auto-expands and B lands inside.
   */
  public function testGrabAndDropAsChildOfCollapsedParentAutoExpands(): void {
    $this->moveTo('B', 'child', 'D');
    $this->assertDepth('B', 2);
    $this->assertPersistedParent('B', 'D');
    $this->assertSame(
      'true',
      $this->row('D')->getAttribute('aria-expanded'),
      'D should be expanded after a child-drop landed inside it.',
    );
    $children = $this->getSession()->getPage()->findAll(
      'css',
      sprintf('li[data-id="%s"] > ul[role="group"] > li[role="treeitem"]', $this->linkIds['D']),
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
        'li[role="treeitem"][data-id="%s"][aria-expanded="true"] > ul[role="group"] > li[role="treeitem"]',
        $this->linkIds['D'],
      ),
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

    $this->assertPersistedParent('B', 'D');
    $this->assertSiblings(
      ['D1', 'B'],
      sprintf('li[data-id="%s"] > ul[role="group"]', $this->linkIds['D']),
    );
  }

  /**
   * Walk a deep child up to the top level, demoting its former parent.
   */
  public function testGrabChildOutOfParent(): void {
    $this->expandRow('A');
    $this->expandRow('A2');
    $this->moveTo('A2a', 'after', 'L');
    $this->assertDepth('A2a', 1);
    $this->assertPersistedParent('A2a', NULL);
    $this->assertSiblings(['A', 'B', 'C', 'D', 'L', 'A2a'], 'ul[role="tree"]');
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
    $this->assertSiblings(['A', 'B', 'C', 'D', 'L'], 'ul[role="tree"]');
    $this->assertPersistedParent('B', NULL);
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
    $this->assertSiblings(['A', 'B', 'C', 'D', 'L'], 'ul[role="tree"]');
    $this->assertPersistedParent('B', NULL);
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
   * Goes to the top of the list, then steps down until the host's
   * data-mtu-drop matches - deterministic regardless of where the grab began.
   *
   * @param string $source
   *   Label of the grabbed row (the node keydowns are dispatched on).
   * @param string $want
   *   The wanted data-mtu-drop value, "kind:pluginId".
   * @param int $max
   *   Safety bound on the number of steps.
   */
  private function stepToDrop(string $source, string $want, int $max = 50): void {
    $this->dispatchKeys($source, array_fill(0, $max, 'ArrowUp'));
    for ($i = 0; $i <= $max; $i++) {
      if ($this->readDrop() === $want) {
        return;
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
      "return document.querySelector('menu-tree').getAttribute('data-mtu-drop');",
    );
  }

  /**
   * Focuses the source row and dispatches a sequence of keydown events on it.
   *
   * The keydowns bubble to the tree, where the <menu-tree> element routes them
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
      const source = document.querySelector('li[role="treeitem"][data-id="$source_id"]');
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
      '!document.querySelector(\'menu-tree[data-mtu-moving="true"]\')'
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
   * Pass NULL for the parent label to count direct top-level rows.
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
   */
  private function assertNoVirtualisedRows(): void {
    $this->getSession()->wait(150);
    $virtualised = $this->getSession()->evaluateScript(
      'return Array.from(document.querySelectorAll(\'menu-tree li[role="treeitem"][data-virtualised="true"]\')).map(r => r.dataset.id);',
    );
    $this->assertSame(
      [],
      $virtualised,
      'Rows stuck marked data-virtualised="true" after the move: ' . json_encode($virtualised),
    );
  }

  /**
   * Asserts every row in DOM has its title element populated.
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
   *   Labels in the order they should appear as direct children.
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
