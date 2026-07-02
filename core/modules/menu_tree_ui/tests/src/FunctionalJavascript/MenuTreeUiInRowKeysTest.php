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
 * Tests that in-row controls keep their own keys instead of the row's.
 *
 * The row's key handlers (the treegrid spine and the menu reorder feature)
 * resolve their target row by climbing from event.target, with no check that
 * the event actually originated on the row. Left unguarded, that means a
 * focused title link loses Enter to the row's expand toggle, and a focused
 * checkbox loses Space to move mode / expand toggle instead of operating.
 * These tests dispatch the key from the control itself and assert it is left
 * alone, plus that plain row navigation still works.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiInRowKeysTest extends WebDriverTestBase {

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
      'drupal-treegrid table[role="treegrid"] tbody tr[role="row"]',
    );
  }

  /**
   * Enter on a focused title link follows the link, not the row's toggle.
   *
   * Dispatched from the link itself (not the row), so this fails against the
   * unguarded handler: it climbs from event.target to the row and toggles
   * expansion instead of leaving Enter to the link's native activation.
   */
  public function testEnterOnTitleLinkIsNotHijacked(): void {
    $this->collapseIfExpanded('A');

    $not_prevented = $this->dispatchKeyOnControl(
      'A',
      '.menu-tree-ui-row__title',
      'Enter',
    );

    $this->assertTrue(
      $not_prevented,
      'Enter on the title link should not be preventDefault-ed by the row.',
    );
    $this->assertSame(
      'false',
      $this->row('A')->getAttribute('aria-expanded'),
      'Enter on the title link should not toggle the row expanded state.',
    );
  }

  /**
   * Space on a focused Enabled checkbox operates it, not the row.
   *
   * Dispatched from the checkbox itself, so this fails against the unguarded
   * handler: it climbs from event.target to the movable row and grabs it into
   * move mode instead of leaving Space to the checkbox.
   */
  public function testSpaceOnCheckboxIsNotHijacked(): void {
    $not_prevented = $this->dispatchKeyOnControl(
      'B',
      'input.treegrid-cell-checkbox[data-column-id="enabled"]',
      ' ',
    );

    $this->assertTrue(
      $not_prevented,
      'Space on the Enabled checkbox should not be preventDefault-ed by the row.',
    );
    $this->assertFalse(
      $this->row('B')->hasClass('menu-tree-ui-row--grabbed'),
      'Space on the Enabled checkbox should not grab the row into move mode.',
    );
  }

  /**
   * Plain row navigation still works: ArrowDown moves the roving tab stop.
   *
   * Confirms the in-row-control guard did not also swallow keys dispatched
   * from the row itself.
   */
  public function testArrowDownOnRowStillNavigates(): void {
    $this->pressKeyOnRow('B', 'ArrowDown');

    $this->assertSame('TR', $this->activeTag(), 'Focus lands on a row.');
    $this->assertSame(
      $this->linkIds['A'],
      $this->activeRowId(),
      'ArrowDown on the row moved focus to the next row.',
    );
  }

  /**
   * Seeds a small menu with a leaf first and a linked parent second.
   *
   * Top level: B (leaf, movable), then A (linked, with child A1). B is
   * deliberately a leaf placed first so that ArrowDown from it always lands
   * on A. A links to a real internal path (rather than <nolink>) so its title
   * renders as a real <a>, not a <span>.
   */
  private function seedMenu(): void {
    Menu::create(['id' => 'test', 'label' => 'Test'])->save();
    $this->linkIds['B'] = $this->createLink('Item B', 'route:<nolink>', '', 0);
    $this->linkIds['A'] = $this->createLink('Item A', 'internal:/', '', 1);
    $this->linkIds['A1'] = $this->createLink('Item A1', 'route:<nolink>', $this->linkIds['A'], 0);
  }

  /**
   * Creates a menu link and returns its plugin ID.
   *
   * @param string $title
   *   The link title.
   * @param string $uri
   *   The link URI.
   * @param string $parent_plugin_id
   *   The parent link plugin ID, or '' for the top level.
   * @param int $weight
   *   The link weight.
   *
   * @return string
   *   The created link's plugin ID.
   */
  private function createLink(string $title, string $uri, string $parent_plugin_id, int $weight): string {
    $entity = MenuLinkContent::create([
      'menu_name' => 'test',
      'title' => $title,
      'link' => ['uri' => $uri],
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
   * Collapses a row if it is currently expanded.
   *
   * Guards tests that assert Enter/Space did not toggle expansion against the
   * "auto-expand for very small menus" behavior possibly having already
   * opened the row on initial load.
   *
   * @param string $label
   *   The row label.
   */
  private function collapseIfExpanded(string $label): void {
    if ($this->row($label)->getAttribute('aria-expanded') === 'true') {
      $this->pressKeyOnRow($label, 'ArrowLeft');
    }
  }

  /**
   * Focuses a row and dispatches a keydown on it.
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
   * Focuses a control inside a row and dispatches a cancelable keydown on it.
   *
   * @param string $label
   *   The row label.
   * @param string $control_selector
   *   A CSS selector for the control, scoped to the row.
   * @param string $key
   *   The KeyboardEvent.key value to dispatch.
   *
   * @return bool
   *   dispatchEvent()'s return value: TRUE unless a handler called
   *   preventDefault() on the event.
   */
  private function dispatchKeyOnControl(string $label, string $control_selector, string $key): bool {
    $id = $this->linkIds[$label];
    $key_json = json_encode($key);
    $control_selector_json = json_encode($control_selector);
    $script = <<<JS
      return (function () {
        const row = document.querySelector('tr[role="row"][data-id="$id"]');
        if (!row) throw new Error('row $label not in DOM');
        const control = row.querySelector($control_selector_json);
        if (!control) throw new Error('control not in row $label');
        row.scrollIntoView({block: 'center'});
        control.focus();
        return control.dispatchEvent(new KeyboardEvent('keydown', {
          key: $key_json, bubbles: true, cancelable: true,
        }));
      })();
    JS;
    return (bool) $this->getSession()->evaluateScript($script);
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
