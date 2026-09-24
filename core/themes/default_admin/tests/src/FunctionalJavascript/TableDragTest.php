<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the markup and storage contract of the forked tabledrag library.
 *
 * The theme replaces 'core/drupal.tabledrag' wholesale with its own fork and
 * then re-overrides a handful of 'Drupal.theme' functions on top of it, so the
 * markup that fork builds at runtime is covered by no test of the core
 * library. Row ordering, keyboard movement and indentation depth are asserted
 * by AdminTableDragTest and are deliberately not repeated here.
 *
 * @see \Drupal\FunctionalJavascriptTests\Theme\AdminTableDragTest
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
final class TableDragTest extends WebDriverTestBase {

  /**
   * Class used to verify that a key press has been handled.
   */
  private const DRAG_TEST_CLASS = 'tabledrag-test-dragging';

  /**
   * Arrow key codes accepted by the tabledrag keyboard handler.
   */
  private const ARROW_KEYS = [
    'left' => 37,
    'right' => 39,
    'up' => 38,
    'down' => 40,
  ];

  /**
   * Selector of the weight column header of the test table.
   */
  private const WEIGHT_HEADER = '#tabledrag-test-table thead th.tabledrag-hide';

  /**
   * Selector of the weight visibility toggle button.
   */
  private const TOGGLE_BUTTON = 'button[data-drupal-selector="tabledrag-toggle-weight"]';

  /**
   * Relative xpath of the cell content wrapper of a draggable row.
   */
  private const CELL_CONTENT = 'child::td[1]/div[contains(concat(" ", normalize-space(@class), " "), " tabledrag-cell-content ")][contains(concat(" ", normalize-space(@class), " "), " js-tabledrag-cell-content ")]';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['tabledrag_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'default_admin';

  /**
   * Tests the browser storage contract of the weight column toggle.
   */
  public function testWeightColumnToggle(): void {
    $this->seedRows(array_flip(range(1, 3)));
    $this->drupalGet('tabledrag_test');
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->assertNotNull($assert_session->waitForElementVisible('css', self::TOGGLE_BUTTON));

    // The weight column is hidden through an inline style, while the marker
    // class identifying that column is written once and never taken off again.
    $assert_session->elementsCount('css', self::WEIGHT_HEADER, 1);
    $this->assertJsCondition($this->weightHeaderDisplayIs('none'));
    // Nothing is stored until a preference has been expressed.
    $this->assertJsCondition($this->storedWeightPreferenceIs(NULL));
    // The button content is markup rather than a bare string: the theme wraps
    // the label in a span carrying the icon class of the current state.
    $assert_session->elementTextContains('css', self::TOGGLE_BUTTON . ' > span.action-link--icon-show', 'Show row weights');

    $page->findButton('Show row weights')->click();

    // Opting in stores the override as the string 'true'.
    $this->assertJsCondition($this->storedWeightPreferenceIs('true'));
    $assert_session->elementsCount('css', self::WEIGHT_HEADER, 1);
    $this->assertJsCondition($this->weightHeaderDisplayIs(''));
    $assert_session->elementTextContains('css', self::TOGGLE_BUTTON . ' > span.action-link--icon-hide', 'Hide row weights');

    $page->findButton('Hide row weights')->click();

    // Opting back out removes the key instead of storing a falsy value, so a
    // later change of the default is not overridden by a stale preference.
    $this->assertJsCondition($this->storedWeightPreferenceIs(NULL));
    $assert_session->elementsCount('css', self::WEIGHT_HEADER, 1);
    $this->assertJsCondition($this->weightHeaderDisplayIs('none'));
    $assert_session->elementTextContains('css', self::TOGGLE_BUTTON . ' > span.action-link--icon-show', 'Show row weights');
  }

  /**
   * Tests that the weight toggle is placed outside the table scroll wrapper.
   */
  public function testWeightToggleWrapperPlacement(): void {
    $this->seedRows(array_flip(range(1, 3)));
    $this->drupalGet('tabledrag_test');
    $assert_session = $this->assertSession();
    $this->assertNotNull($assert_session->waitForElementVisible('css', 'div[data-drupal-selector="tabledrag-toggle-weight-wrapper"]'));

    // A table that has a header is rendered inside a horizontally scrolling
    // wrapper.
    $assert_session->elementExists('css', 'div.layer-wrapper > div.table-scroll-wrapper > table#tabledrag-test-table');

    // The toggle is inserted before that wrapper rather than before the table,
    // so the control does not scroll out of view with the table.
    $assert_session->elementExists('xpath', '//div' . self::hasClass('tabledrag-toggle-weight-wrapper') . '[@data-drupal-selector="tabledrag-toggle-weight-wrapper"]/following-sibling::*[1][self::div]' . self::hasClass('table-scroll-wrapper'));
    $assert_session->elementNotExists('css', 'div.table-scroll-wrapper div.tabledrag-toggle-weight-wrapper');
  }

  /**
   * Tests the drag handle markup built by the theme.
   */
  public function testDragHandleMarkup(): void {
    $this->seedRows(array_flip(range(1, 3)));
    $this->drupalGet('tabledrag_test');
    $assert_session = $this->assertSession();
    $this->assertNotNull($assert_session->waitForElementVisible('css', '#tabledrag-test-table a.tabledrag-handle'));

    // The handle is a bare anchor carrying a behavior hook class; the theme
    // draws the icon on the anchor itself instead of on a nested element.
    $this->assertNotEmpty($this->findHandle($this->findRowById('1')));
    $assert_session->elementNotExists('css', '#tabledrag-test-table a.tabledrag-handle div.handle');

    // This table declares a parent relationship, so its rows can be moved in
    // any direction and the vertical-only modifier class is absent.
    $assert_session->elementNotExists('css', '#tabledrag-test-table a.tabledrag-handle.tabledrag-handle-y');

    $this->drupalGet('tabledrag_test_nested');
    $this->assertNotNull($assert_session->waitForElementVisible('css', '#tabledrag-test-parent-table a.tabledrag-handle'));

    // The parent table declares no parent relationship, so its rows may only
    // be reordered and the handle is marked accordingly.
    $row = $this->findRowById('parent_2', 'tabledrag-test-parent-table');
    $this->assertNotEmpty($row->find('xpath', self::CELL_CONTENT . '/a' . self::hasClass('tabledrag-handle-y')));
  }

  /**
   * Tests that the first cell of a draggable row is wrapped for its label.
   */
  public function testDragCellContentWrapper(): void {
    $this->seedRows(array_flip(range(1, 3)));
    $this->drupalGet('tabledrag_test');
    $assert_session = $this->assertSession();
    $this->assertNotNull($assert_session->waitForElementVisible('css', '#tabledrag-test-table div.js-tabledrag-cell-content'));
    $row = $this->findRowById('1');

    // The original cell content is moved into an item wrapper, so that a long
    // label wraps against the label instead of against the row.
    $item = $row->find('xpath', self::CELL_CONTENT . '/div' . self::hasClass('tabledrag-cell-content__item'));
    $this->assertNotEmpty($item);
    $this->assertStringContainsString('Row with id 1', $item->getText());

    // The handle is moved back out of the item wrapper and becomes the first
    // child of the cell content wrapper.
    $this->assertNotEmpty($row->find('xpath', self::CELL_CONTENT . '/*[1][self::a]' . self::hasClass('js-tabledrag-handle')));
    $assert_session->elementNotExists('css', '#tabledrag-test-table div.tabledrag-cell-content__item > a.js-tabledrag-handle');
  }

  /**
   * Tests the indentation markup added while nesting a row.
   */
  public function testIndentationMarkup(): void {
    $this->seedRows(array_flip(range(1, 3)));
    $this->drupalGet('tabledrag_test');
    $assert_session = $this->assertSession();
    $this->assertNotNull($assert_session->waitForElementVisible('css', '#tabledrag-test-table a.tabledrag-handle'));

    // Nest a row, so that the library has to build an indentation element.
    $this->moveRowWithKeyboard($this->findRowById('2'), 'right');
    $row = $this->findRowById('2');

    // A new indentation is relocated into the cell content wrapper, because
    // only inside that wrapper does it line up with the handle.
    $indentation = $row->find('xpath', self::CELL_CONTENT . '/div' . self::hasClass('js-indentation') . self::hasClass('indentation'));
    $this->assertNotEmpty($indentation);
    $assert_session->elementNotExists('xpath', '//table[@id="tabledrag-test-table"]/tbody/tr/td[1]/div' . self::hasClass('js-indentation'));

    // The theme draws the hierarchy with an inline SVG tree instead of the
    // non-breaking space that the core library emits. Both steps are matched
    // by local name, because the elements of that tree are not in the
    // namespace of the document, and the axis of the first one is spelled
    // out, because the xpath manipulator of Mink strips a leading "*" while
    // it prepends the expression of the container.
    $assert_session->elementsCount('xpath', "child::*[local-name()='svg']" . self::hasClass('tree'), 1, $indentation);
    $assert_session->elementsCount('xpath', ".//*[local-name()='path']" . self::hasClass('tree__item'), 4, $indentation);
  }

  /**
   * Tests the changed marker and the unsaved changes warning.
   */
  public function testChangedMarkerAndWarning(): void {
    $this->seedRows(array_flip(range(1, 3)));
    $this->drupalGet('tabledrag_test');
    $assert_session = $this->assertSession();
    $this->assertNotNull($assert_session->waitForElementVisible('css', '#tabledrag-test-table a.tabledrag-handle'));
    $assert_session->elementNotExists('css', 'abbr.tabledrag-changed');
    $assert_session->elementNotExists('css', 'form[data-tabledrag-save-warning]');

    $this->moveRowWithKeyboard($this->findRowById('2'), 'up');
    $row = $this->findRowById('2');

    // The marker is placed directly after the handle instead of at the end of
    // the cell, so that it reads as part of the drag control.
    $this->assertNotEmpty($row->find('xpath', self::CELL_CONTENT . '/a' . self::hasClass('js-tabledrag-handle') . '/following-sibling::*[1][self::abbr]' . self::hasClass('warning') . self::hasClass('tabledrag-changed') . self::hasClass('js-tabledrag-changed-marker')));

    // The warning is rendered as a regular warning message holding the same
    // marker, and it is only ever inserted once.
    $this->assertNotNull($assert_session->waitForElementVisible('css', 'div.tabledrag-changed-warning.messages.messages--warning[role="alert"]'));
    $assert_session->elementsCount('css', 'div.tabledrag-changed-warning', 1);
    $assert_session->elementExists('css', 'div.tabledrag-changed-warning abbr.tabledrag-changed');

    // The form records that the warning has been shown, which suppresses the
    // fade in when the table is rebuilt.
    $assert_session->elementExists('css', 'form[data-tabledrag-save-warning="true"]');
  }

  /**
   * Tests the classes applied while a row is held by the pointer.
   */
  public function testPointerDragClasses(): void {
    $this->seedRows(array_flip(range(1, 3)));
    $this->drupalGet('tabledrag_test');
    $this->assertNotNull($this->assertSession()->waitForElementVisible('css', '#tabledrag-test-table a.tabledrag-handle'));
    $observed = $this->observePointerDrag($this->findHandle($this->findRowById('2')));

    // Picking a row up marks that row and switches the document over to the
    // drag cursor of the table orientation; this table moves in any direction.
    $this->assertContains('drag', $observed['rowHeld']);
    $this->assertContains('drag', $observed['bodyHeld']);
    $this->assertNotContains('drag-y', $observed['bodyHeld']);

    // Dropping it hands the mark over to the previous-row class and restores
    // the document cursor.
    $this->assertNotContains('drag', $observed['rowDropped']);
    $this->assertContains('drag-previous', $observed['rowDropped']);
    $this->assertNotContains('drag', $observed['bodyDropped']);

    $this->drupalGet('tabledrag_test_nested');
    $this->assertNotNull($this->assertSession()->waitForElementVisible('css', '#tabledrag-test-parent-table a.tabledrag-handle'));
    $row = $this->findRowById('parent_2', 'tabledrag-test-parent-table');
    $observed = $this->observePointerDrag($this->findHandle($row));

    // A table without a parent relationship only reorders, so the document is
    // given the vertical-only drag cursor instead.
    $this->assertContains('drag-y', $observed['bodyHeld']);
    $this->assertNotContains('drag', $observed['bodyHeld']);
    $this->assertNotContains('drag-y', $observed['bodyDropped']);
  }

  /**
   * Tests the tree classes applied to the indentations of a held row group.
   */
  public function testTreeIndentationClasses(): void {
    $this->seedRows(array_flip(range(1, 5)));
    $this->drupalGet('tabledrag_test_nested');
    $assert_session = $this->assertSession();
    $this->assertNotNull($assert_session->waitForElementVisible('css', '#tabledrag-test-table a.tabledrag-handle'));

    // Build a two level hierarchy below row 1 of the nested table.
    $this->moveRowWithKeyboard($this->findRowById('2'), 'right');
    $this->moveRowWithKeyboard($this->findRowById('3'), 'right');
    $this->moveRowWithKeyboard($this->findRowById('3'), 'right');

    // The tree classes exist only while a row group is held. Hold row 1 with
    // a key press that cannot move it: the first row can never be nested.
    $handle = $this->findHandle($this->findRowById('1'));
    $handle->focus();
    $this->pressArrow($handle, 'right');

    // The first child of the held row opens the tree.
    $first_child = $this->findRowById('2');
    $this->assertNotEmpty($first_child->find('xpath', self::CELL_CONTENT . '/div' . self::hasClass('js-indentation') . self::hasClass('tree-child') . self::hasClass('tree-child-first')));

    // The last child closes it, and its deeper indentation is drawn as a
    // horizontal branch rather than as another vertical line.
    $last_child = $this->findRowById('3');
    $this->assertNotEmpty($last_child->find('xpath', self::CELL_CONTENT . '/div' . self::hasClass('js-indentation') . self::hasClass('tree-child') . self::hasClass('tree-child-last')));
    $this->assertNotEmpty($last_child->find('xpath', self::CELL_CONTENT . '/div' . self::hasClass('js-indentation') . self::hasClass('tree-child-horizontal')));

    // Releasing the row group takes every tree class off again.
    $handle->blur();
    $assert_session->elementNotExists('xpath', '//table[@id="tabledrag-test-table"]//div' . self::hasClass('tree-child'));
    $assert_session->elementNotExists('xpath', '//table[@id="tabledrag-test-table"]//div' . self::hasClass('tree-child-horizontal'));
  }

  /**
   * Seeds the rows of the test table.
   *
   * @param array $rows
   *   Row definitions, keyed by row ID.
   */
  private function seedRows(array $rows): void {
    $this->container->get('state')->set('tabledrag_test_table', $rows);
  }

  /**
   * Builds an xpath predicate matching a single class of an element.
   *
   * @param string $class
   *   The class name to match.
   *
   * @return string
   *   The xpath predicate.
   */
  private static function hasClass(string $class): string {
    return sprintf('[contains(concat(" ", normalize-space(@class), " "), " %s ")]', $class);
  }

  /**
   * Builds a condition asserting the stored weight visibility preference.
   *
   * @param string|null $value
   *   The expected stored value, or NULL if no value is expected at all.
   *
   * @return string
   *   The JavaScript condition.
   */
  private function storedWeightPreferenceIs(?string $value): string {
    $expected = json_encode($value, JSON_THROW_ON_ERROR);
    return "localStorage.getItem('Drupal.tableDrag.showWeight') === $expected";
  }

  /**
   * Builds a condition asserting the inline display of the weight header.
   *
   * @param string $display
   *   The expected inline display value.
   *
   * @return string
   *   The JavaScript condition.
   */
  private function weightHeaderDisplayIs(string $display): string {
    $expected = json_encode($display, JSON_THROW_ON_ERROR);
    return sprintf("document.querySelector('%s').style.display === %s", self::WEIGHT_HEADER, $expected);
  }

  /**
   * Finds a row of a test table by its row ID.
   *
   * @param string $id
   *   The ID of the row.
   * @param string $table_id
   *   The HTML ID of the table holding the row.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The row element.
   */
  private function findRowById(string $id, string $table_id = 'tabledrag-test-table'): NodeElement {
    $row = $this->getSession()->getPage()->find('xpath', "//table[@id='$table_id']/tbody/tr[.//input[@name='table[$id][id]']]");
    $this->assertNotEmpty($row);
    return $row;
  }

  /**
   * Finds the drag handle of a row.
   *
   * The handle is looked up along its exact path, so that a handle of a nested
   * table is never returned for a row of the surrounding table.
   *
   * @param \Behat\Mink\Element\NodeElement $row
   *   The row element.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The handle element.
   */
  private function findHandle(NodeElement $row): NodeElement {
    $handle = $row->find('xpath', self::CELL_CONTENT . '/a' . self::hasClass('tabledrag-handle') . self::hasClass('js-tabledrag-handle'));
    $this->assertNotEmpty($handle);
    return $handle;
  }

  /**
   * Moves a row with the keyboard and releases it again.
   *
   * @param \Behat\Mink\Element\NodeElement $row
   *   The row to move.
   * @param string $arrow
   *   One of 'left', 'right', 'up' or 'down'.
   */
  private function moveRowWithKeyboard(NodeElement $row, string $arrow): void {
    $handle = $this->findHandle($row);
    $handle->focus();
    $this->pressArrow($handle, $arrow);
    $handle->blur();
  }

  /**
   * Presses an arrow key on a focused handle without releasing the row.
   *
   * @param \Behat\Mink\Element\NodeElement $handle
   *   The handle element.
   * @param string $arrow
   *   One of 'left', 'right', 'up' or 'down'.
   */
  private function pressArrow(NodeElement $handle, string $arrow): void {
    $xpath = json_encode($handle->getXpath(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $class = self::DRAG_TEST_CLASS;
    // The test module removes this class again on the next key press, which
    // makes the handling of that key press observable.
    $this->getSession()->executeScript(<<<JS
      document.evaluate($xpath, document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null)
        .singleNodeValue.classList.add('$class');
      JS);
    $page = $this->getSession()->getPage();
    $this->assertTrue($page->waitFor(1, fn (): bool => $handle->hasClass($class)), 'The marker class was added to the handle.');
    $handle->keyDown(self::ARROW_KEYS[$arrow]);
    $handle->keyUp(self::ARROW_KEYS[$arrow]);
    $this->assertTrue($page->waitFor(1, fn (): bool => !$handle->hasClass($class)), 'The key press was handled by the table.');
  }

  /**
   * Picks a row up with the pointer and drops it, recording both states.
   *
   * The classes under test exist only between the pointer going down and
   * coming back up, so both events are dispatched in a single round trip.
   *
   * @param \Behat\Mink\Element\NodeElement $handle
   *   The handle to press.
   *
   * @return array
   *   The classes of the document body and of the dragged row, both while the
   *   row is held and after it has been dropped.
   */
  private function observePointerDrag(NodeElement $handle): array {
    $xpath = json_encode($handle->getXpath(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    return $this->getSession()->evaluateScript(<<<JS
      (function () {
        const handle = document.evaluate($xpath, document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
        const row = handle.closest('tr');
        const box = handle.getBoundingClientRect();
        const options = {
          bubbles: true,
          clientX: Math.round(box.left + box.width / 2),
          clientY: Math.round(box.top + box.height / 2),
        };
        handle.dispatchEvent(new MouseEvent('mousedown', options));
        const held = {body: [...document.body.classList], row: [...row.classList]};
        document.dispatchEvent(new MouseEvent('mouseup', options));
        return {
          bodyHeld: held.body,
          rowHeld: held.row,
          bodyDropped: [...document.body.classList],
          rowDropped: [...row.classList],
        };
      })()
      JS);
  }

}
