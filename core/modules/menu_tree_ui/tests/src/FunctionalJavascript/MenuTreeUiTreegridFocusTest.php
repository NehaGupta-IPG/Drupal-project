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
   * Menu link plugin IDs, keyed by the labels used in the tests.
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
      'menu-tree table[role="treegrid"] tbody tr[role="row"]',
    );
  }

  /**
   * The tree is a single tab stop on a row; handles are never tab stops.
   */
  public function testSingleRowTabStop(): void {
    $rows_at_zero = (int) $this->getSession()->evaluateScript(
      "return document.querySelectorAll('menu-tree tr[role=\"row\"][tabindex=\"0\"]').length;",
    );
    $this->assertSame(1, $rows_at_zero, 'Exactly one row is the tab stop.');

    $handles_at_zero = (int) $this->getSession()->evaluateScript(
      "return document.querySelectorAll('menu-tree .menu-tree-ui-row__handle[tabindex=\"0\"]').length;",
    );
    $this->assertSame(0, $handles_at_zero, 'No handle is a tab stop.');

    $handles_not_minus_one = (int) $this->getSession()->evaluateScript(
      "return document.querySelectorAll('menu-tree .menu-tree-ui-row__handle:not([tabindex=\"-1\"])').length;",
    );
    $this->assertSame(0, $handles_not_minus_one, 'Every handle is tabindex="-1".');
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
   * Plain navigation selects nothing; only a picked-up row is selected.
   */
  public function testSelectionOnlyWhileGrabbed(): void {
    $this->pressKeyOnRow('A', 'ArrowDown');
    $this->assertSame(
      0,
      (int) $this->getSession()->evaluateScript(
        "return document.querySelectorAll('menu-tree tr[aria-selected=\"true\"]').length;",
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
        "return document.querySelectorAll('menu-tree tr[aria-selected=\"true\"]').length;",
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
    $this->assertNotSame('', trim($hint->getText()), 'The interaction hint has text.');
  }

  /**
   * Seeds a small menu with a leaf first and a parent second.
   *
   * Top level: B (leaf), then A (with A1, A2 (with A2a)). B is deliberately a
   * leaf placed first so that ArrowDown from it always lands on A, regardless of
   * the "auto-expand for very small menus" behaviour: a leaf has no children to
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
   * Creates a menu link and returns its plugin ID.
   *
   * @param string $title
   *   The link title.
   * @param string $parent_plugin_id
   *   The parent link plugin ID, or '' for the top level.
   * @param int $weight
   *   The link weight.
   *
   * @return string
   *   The created link's plugin ID.
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
   * Finds a row by its label.
   *
   * @param string $label
   *   The label used when seeding.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The row element.
   */
  private function row(string $label): NodeElement {
    $row = $this->getSession()->getPage()->find(
      'css',
      sprintf('tr[role="row"][data-id="%s"]', $this->linkIds[$label]),
    );
    $this->assertNotNull($row, sprintf('Row %s is in the DOM.', $label));
    return $row;
  }

  /**
   * Focuses a row and dispatches a keydown on it.
   *
   * Deliberately focuses the row itself rather than forcing focus onto a
   * handle, so the test exercises the real focus model instead of papering
   * over it.
   *
   * @param string $label
   *   The row label.
   * @param string $key
   *   The KeyboardEvent.key value to dispatch.
   */
  private function pressKeyOnRow(string $label, string $key): void {
    $id = $this->linkIds[$label];
    $key_json = json_encode($key);
    $script = <<<JS
      const row = document.querySelector('tr[role="row"][data-id="$id"]');
      if (!row) throw new Error('row $label not in DOM');
      row.scrollIntoView({block: 'center'});
      row.focus();
      row.dispatchEvent(new KeyboardEvent('keydown', {
        key: $key_json, bubbles: true, cancelable: true,
      }));
    JS;
    $this->getSession()->executeScript($script);
  }

  /**
   * The tag name of the currently focused element.
   *
   * @return string|null
   *   The uppercase tag name, or NULL when nothing is focused.
   */
  private function activeTag(): ?string {
    return $this->getSession()->evaluateScript(
      'return document.activeElement ? document.activeElement.tagName : null;',
    );
  }

  /**
   * The data-id of the currently focused row, when a row is focused.
   *
   * @return string|null
   *   The focused row's data-id, or NULL when the focus is not on a row.
   */
  private function activeRowId(): ?string {
    return $this->getSession()->evaluateScript(
      "return document.activeElement && document.activeElement.matches('tr[role=\"row\"]')"
      . ' ? document.activeElement.dataset.id : null;',
    );
  }

}
