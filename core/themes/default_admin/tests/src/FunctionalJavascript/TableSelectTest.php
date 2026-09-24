<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

// cspell:ignore vbo

/**
 * Tests the table selection behavior of the theme.
 *
 * Two code paths are covered: the sticky bulk actions form built by
 * js/tableselect.js on a views table, and migration/js/tableselect.js, which
 * default_admin.info.yml maps over misc/tableselect.js in the
 * core/drupal.tableselect library.
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
final class TableSelectTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * The number of nodes listed by the content view.
   */
  private const ROW_COUNT = 5;

  /**
   * The selector of the bulk actions container.
   *
   * @see \Drupal\default_admin\Hook\FormHooks::formAlter()
   */
  private const BULK_ACTIONS = '[data-drupal-views-bulk-actions]';

  /**
   * The selector of the select all checkbox of the rendered table.
   */
  private const SELECT_ALL = '.table-scroll-wrapper th.select-all input[type="checkbox"]';

  /**
   * The selector of the select all checkbox of the cloned table header.
   */
  private const CLONED_SELECT_ALL = 'table.sticky-table-header th.select-all input[type="checkbox"]';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'views', 'form_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'default_admin';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createContentType(['type' => 'page']);
    for ($index = 0; $index < self::ROW_COUNT; $index++) {
      $this->createNode(['title' => 'Test node ' . $index]);
    }

    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'access content overview',
      'edit any page content',
    ]));
  }

  /**
   * Tests the state the bulk actions form exposes to the stylesheet.
   */
  public function testBulkActionsState(): void {
    $this->drupalGet('admin/content');
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // The row checkbox handler is bound to the window load event, so the
    // container carries the unselected state once the page has settled.
    $this->assertNotNull($assert_session->waitForElement('css', self::BULK_ACTIONS . '[data-drupal-sticky-vbo="false"]'));

    // Every row of the views form is flagged when the behavior attaches, so no
    // row of the form is ever left without the flag.
    $assert_session->elementsCount('css', '.views-form tbody tr.views-form__bulk-operations-row', self::ROW_COUNT);
    $this->assertJsCondition('document.querySelectorAll(".views-form tr:not(.views-form__bulk-operations-row)").length === 0');

    // Selecting a row switches the container to its sticky state.
    $page->checkField('node_bulk_form[0]');
    $this->assertNotNull($assert_session->waitForElement('css', self::BULK_ACTIONS . '[data-drupal-sticky-vbo="true"]'));

    // Clearing the selection switches it back.
    $page->uncheckField('node_bulk_form[0]');
    $this->assertNotNull($assert_session->waitForElement('css', self::BULK_ACTIONS . '[data-drupal-sticky-vbo="false"]'));

    // The row flag is not bound to the selection.
    $this->assertJsCondition('document.querySelectorAll(".views-form tr:not(.views-form__bulk-operations-row)").length === 0');
  }

  /**
   * Tests row selection, range selection and the select all checkbox.
   */
  public function testRangeSelection(): void {
    $this->drupalGet('admin/content');
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // The library syncs the checkbox of the cloned header by writing its
    // checked property without the attribute of the same name. The CSS
    // selectors of Mink are translated into xpath expressions testing that
    // attribute, so the state of the clone is read in the browser instead.
    $cloned_checked = sprintf("document.querySelectorAll('%s:checked').length", self::CLONED_SELECT_ALL);

    // The views table header ships without a checkbox, so exactly one is added
    // to it, while the cloned sticky header carries the one rendered by
    // views-view-table.html.twig.
    $select_all = $assert_session->waitForElement('css', self::SELECT_ALL);
    $this->assertNotNull($select_all);
    $assert_session->elementsCount('css', self::SELECT_ALL, 1);
    $assert_session->elementsCount('css', self::CLONED_SELECT_ALL, 1);
    $this->assertSame('Select all rows in this table', $select_all->getAttribute('title'));

    // Selecting a single row marks that row only.
    $page->checkField('node_bulk_form[0]');
    $this->assertJsCondition('document.querySelectorAll(".views-table tbody tr.selected").length === 1');

    // Holding shift while clicking another row selects the whole range between
    // the two rows.
    $this->clickBulkFormCheckbox(self::ROW_COUNT - 1, TRUE);
    $this->assertJsCondition(sprintf('document.querySelectorAll(".views-table tbody tr.selected").length === %d', self::ROW_COUNT));
    $this->assertJsCondition(sprintf('document.querySelectorAll(".views-table tbody input:checked").length === %d', self::ROW_COUNT));

    // With the range covering all rows, the select all checkbox of the
    // rendered table flips its title, and the checkbox of the cloned sticky
    // header is kept in sync through the table scroll wrapper.
    $this->assertNotNull($assert_session->waitForElement('css', '.table-scroll-wrapper th.select-all input[title="Deselect all rows in this table"]'));
    $this->assertJsCondition($cloned_checked . ' === 1');

    // Removing a single row from the selection reverts both.
    $this->clickBulkFormCheckbox(0);
    $this->assertJsCondition(sprintf('document.querySelectorAll(".views-table tbody tr.selected").length === %d', self::ROW_COUNT - 1));
    $this->assertNotNull($assert_session->waitForElement('css', '.table-scroll-wrapper th.select-all input[title="Select all rows in this table"]'));
    $this->assertJsCondition($cloned_checked . ' === 0');
  }

  /**
   * Tests the select all checkbox of a plain tableselect element.
   *
   * The form of this route is the only tableselect element in core that opts
   * out of Ajax, which keeps the table markup stable while it is clicked.
   *
   * @see \Drupal\form_test\Form\FormTestTableSelectDisabledRowsForm::buildForm()
   */
  public function testPlainTableSelect(): void {
    $this->drupalGet('form_test/tableselect/disabled-rows/multiple-true');
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // The table template renders a select all checkbox into the header cell
    // itself. Only the version of the library mapped in by the theme keeps
    // that checkbox instead of adding a second one next to it.
    $this->assertNotNull($assert_session->waitForElement('css', '.table-scroll-wrapper table[data-once~="table-select"]'));
    $assert_session->elementsCount('css', self::SELECT_ALL, 1);

    $select_all = $page->find('css', self::SELECT_ALL);
    $this->assertNotNull($select_all);
    $select_all->click();

    // The title flips, and only the two enabled rows are selected.
    $this->assertNotNull($assert_session->waitForElement('css', '.table-scroll-wrapper th.select-all input[title="Deselect all rows in this table"]'));
    $this->assertJsCondition('document.querySelectorAll("table tbody tr.selected").length === 2');
    $assert_session->checkboxNotChecked('tableselect[row2]');

    // Clicking it again clears the selection and restores the title.
    $select_all->click();
    $this->assertNotNull($assert_session->waitForElement('css', '.table-scroll-wrapper th.select-all input[title="Select all rows in this table"]'));
    $this->assertJsCondition('document.querySelectorAll("table tbody tr.selected").length === 0');
  }

  /**
   * Clicks a row checkbox of the bulk form, optionally holding shift.
   *
   * @param int $delta
   *   The row index of the checkbox to click.
   * @param bool $shift
   *   (optional) Whether the shift key is held down during the click. Defaults
   *   to FALSE.
   */
  private function clickBulkFormCheckbox(int $delta, bool $shift = FALSE): void {
    $modifier = $shift ? 'true' : 'false';
    $this->getSession()->executeScript(<<<JS
      document
        .querySelector('input[name="node_bulk_form[$delta]"]')
        .dispatchEvent(new MouseEvent('click', { bubbles: true, shiftKey: $modifier }));
      JS);
  }

}
