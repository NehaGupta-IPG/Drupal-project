<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the menu_tree_ui working-copy Save and Cancel flows end-to-end.
 *
 * A drag or keyboard move no longer writes to storage; it records the move in
 * a per-user server-side working copy and overlays it on the rendered tree.
 * The change is only persisted when the user clicks Save; Cancel discards it.
 * These tests drive one drag, then assert the unsaved notice, Save (persists)
 * and Cancel (discards) against the reloaded menu_link_content entities.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiSaveCancelTest extends WebDriverTestBase {

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
      'menu-tree table[role="treegrid"] tbody tr[role="row"]'
    );
  }

  /**
   * Seeds a menu with three top-level links A, B, C (A has one child, A1).
   *
   * A1 lets a test expand A and check that a Save keeps it open.
   */
  private function seedMenu(): void {
    Menu::create([
      'id' => 'test',
      'label' => 'Test',
    ])->save();

    $weight = 0;
    foreach (['A', 'B', 'C'] as $label) {
      $this->linkIds[$label] = $this->createLink($label, '', $weight++);
    }
    $this->linkIds['A1'] = $this->createLink('A1', $this->linkIds['A'], 0);
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
   * The unsaved notice carries js-hide until the first move is recorded.
   */
  public function testUnsavedNoticeAppearsAfterMove(): void {
    $notice = $this->getSession()->getPage()->find('css', '.menu-tree-ui__unsaved');
    $this->assertNotNull($notice, 'The unsaved notice element should be present.');
    $this->assertTrue(
      $notice->hasClass('js-hide'),
      'The unsaved notice should start hidden (js-hide).',
    );

    $this->moveCBeforeA();

    $this->assertFalse(
      $this->getSession()->getPage()->find('css', '.menu-tree-ui__unsaved')->hasClass('js-hide'),
      'The unsaved notice should be revealed after a move is recorded.',
    );
  }

  /**
   * Save persists the pending reorder: top-level order becomes C, A, B.
   */
  public function testSavePersistsMoves(): void {
    $this->moveCBeforeA();

    $this->getSession()->getPage()->find('css', '.menu-tree-ui__save')->click();
    // Save no longer reloads the page; the unsaved notice hiding again is the
    // signal that the working copy was committed.
    $this->waitForSave();

    $a = $this->reloadEntityByLabel('A');
    $b = $this->reloadEntityByLabel('B');
    $c = $this->reloadEntityByLabel('C');
    $this->assertLessThan(
      $a->getWeight(),
      $c->getWeight(),
      'After Save, C should sort before A.',
    );
    $this->assertLessThan(
      $b->getWeight(),
      $a->getWeight(),
      'After Save, A should sort before B.',
    );

    // The saved order stays on screen without a reload.
    $this->assertSiblings(['C', 'A', 'B'], $this->childRowsSelector(NULL));
  }

  /**
   * Save keeps expanded sections open (it does not reload/collapse the tree).
   */
  public function testSaveKeepsExpansionOpen(): void {
    // Open A so its child A1 is loaded and visible.
    $toggle = $this->getSession()->getPage()->find(
      'css',
      sprintf('tr[role="row"][data-id="%s"] button.menu-tree-ui-row__toggle', $this->linkIds['A']),
    );
    $this->assertNotNull($toggle, 'A should have an expand toggle.');
    $toggle->click();
    $this->assertSession()->waitForElement(
      'css',
      sprintf('tr[role="row"][data-parent-id="%s"]', $this->linkIds['A']),
    );

    $this->moveCBeforeA();
    $this->getSession()->getPage()->find('css', '.menu-tree-ui__save')->click();
    $this->waitForSave();

    // A is still expanded and its child is still in the DOM - no collapse.
    $this->assertSame(
      'true',
      $this->getSession()->getPage()
        ->find('css', sprintf('tr[role="row"][data-id="%s"]', $this->linkIds['A']))
        ->getAttribute('aria-expanded'),
      'A should remain expanded after Save.',
    );
    $this->assertNotNull(
      $this->getSession()->getPage()->find(
        'css',
        sprintf('tr[role="row"][data-id="%s"]', $this->linkIds['A1']),
      ),
      'A1 should still be present after Save (the tree was not rebuilt).',
    );
  }

  /**
   * Waits for a Save to commit, signalled by the unsaved notice hiding again.
   */
  private function waitForSave(): void {
    $this->assertJsCondition(
      "document.querySelector('.menu-tree-ui__unsaved').classList.contains('js-hide')",
    );
  }

  /**
   * Cancel discards the pending reorder: the seed order A, B, C is unchanged.
   */
  public function testCancelDiscardsMoves(): void {
    $this->moveCBeforeA();

    $this->getSession()->executeScript('window.__mtuReload = true;');
    $this->getSession()->getPage()->find('css', '.menu-tree-ui__cancel')->click();
    $this->waitForReload();

    $a = $this->reloadEntityByLabel('A');
    $b = $this->reloadEntityByLabel('B');
    $c = $this->reloadEntityByLabel('C');
    $this->assertLessThan(
      $b->getWeight(),
      $a->getWeight(),
      'After Cancel, A should still sort before B.',
    );
    $this->assertLessThan(
      $c->getWeight(),
      $b->getWeight(),
      'After Cancel, B should still sort before C.',
    );
  }

  /**
   * Drags row C into the "before" zone of row A and waits for the commit.
   *
   * Fires the same synthetic PointerEvent sequence the drag test uses against
   * the live DragController, then waits for the host to clear data-mtu-moving
   * and for the top-level order to read A, C (C lifted above its old slot).
   */
  private function moveCBeforeA(): void {
    $source_id = $this->linkIds['C'];
    $target_id = $this->linkIds['A'];

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
      const endY = tRect.top + tRect.height * 0.1;
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
    $this->getSession()->wait(
      5000,
      '!document.querySelector(\'menu-tree[data-mtu-moving="true"]\')'
      . ' && !document.querySelector(\'.menu-tree-ui-row--dragging\')'
      . ' && !document.querySelector(\'[data-loading="true"]\')',
    );

    $this->assertSiblings(['C', 'A', 'B'], $this->childRowsSelector(NULL));
  }

  /**
   * Asserts the ordered DOM rows that are direct children of a parent.
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

  /**
   * Waits for the js-driven full-page reload to complete.
   *
   * A sentinel (window.__mtuReload) is set before the Save/Cancel click; the
   * reload tears the window down and clears it, so polling for its absence
   * detects the navigation. The tree's first row is then awaited to confirm
   * the new page upgraded.
   */
  private function waitForReload(): void {
    $this->getSession()->wait(10000, 'typeof window.__mtuReload === "undefined"');
    $this->assertSession()->waitForElement(
      'css',
      'menu-tree table[role="treegrid"] tbody tr[role="row"]',
    );
  }

  /**
   * Reloads the link entity whose title equals $label, fresh from storage.
   */
  private function reloadEntityByLabel(string $label): MenuLinkContentInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
    $storage->resetCache();
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', $label)
      ->execute();
    $this->assertNotEmpty($ids, "Entity titled $label not found in storage.");
    /** @var \Drupal\menu_link_content\MenuLinkContentInterface $entity */
    $entity = $storage->load(reset($ids));
    return $entity;
  }

}
