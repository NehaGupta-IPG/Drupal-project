<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

// cspell:ignore syncscroll

/**
 * Tests the sticky table header of the theme.
 *
 * default_admin.info.yml unsets misc/tableheader.js from the
 * core/drupal.tableheader library and extends that library with
 * default_admin/tableheader, so migration/js/tableheader.js is the only sticky
 * header implementation of the theme. Unlike the core script it does not build
 * the header clone: table.html.twig and views-view-table.html.twig render a
 * second, hidden table, and the script only reveals it, marks the observed
 * table and keeps the column widths of the clone in step with the real table.
 * The horizontal position of the two is kept in step by
 * migration/js/scroll_sync.js instead, which the tableheader library depends
 * on.
 *
 * Both template variants are covered, because the vertical offset the script
 * writes depends on which class the table carries: the table render element
 * adds "sticky-header", while the views table template adds "sticky-enabled
 * position-sticky".
 *
 * @see \Drupal\Core\Render\Element\Table::preRenderTable()
 * @see \Drupal\views\Hook\ViewsThemeHooks::preprocessViewsViewTable()
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
final class TableHeaderTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * The selector of the statically rendered header clone.
   *
   * @see core/themes/default_admin/templates/dataset/table.html.twig
   * @see core/themes/default_admin/templates/views/views-view-table.html.twig
   */
  private const CLONE_SELECTOR = 'table.sticky-table-header.syncscroll[name="sticky-header"]';

  /**
   * The selector of the scroll container of the real table.
   */
  private const WRAPPER_SELECTOR = '.table-scroll-wrapper.syncscroll[name="sticky-header"]';

  /**
   * The selector of the observed table of the user permissions form.
   */
  private const TABLE_ELEMENT = 'table.sticky-header.permissions';

  /**
   * The selector of the observed table of the content view.
   */
  private const VIEWS_TABLE = 'table.views-table.position-sticky';

  /**
   * The number of additional roles the scroll test adds.
   *
   * Each role adds a column to the permissions table. Ten of them push the
   * intrinsic width of the table well past a 640 pixel wide window, so the
   * scroll container really has something to scroll.
   */
  private const EXTRA_ROLES = 10;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'views'];

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
    for ($index = 0; $index < 3; $index++) {
      $this->createNode(['title' => 'Test node ' . $index]);
    }

    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'access content overview',
      'administer permissions',
    ]));
  }

  /**
   * Tests the sticky header of a table render element.
   *
   * @see \Drupal\user\Form\UserPermissionsForm::buildForm()
   */
  public function testStickyHeaderOnTableElement(): void {
    $this->drupalGet('admin/people/permissions');
    $assert_session = $this->assertSession();

    // The permissions form sets '#sticky' => TRUE, so the table render element
    // adds the "sticky-header" class the script keys on.
    $this->assertNotNull($assert_session->waitForElement('css', self::TABLE_ELEMENT . '[data-once~="adminTableHeaderSticky"]'));

    $this->assertStickyHeaderRevealed();
    $this->assertProcessedMarker(self::TABLE_ELEMENT);

    // A table without the "sticky-enabled" class is offset by a single pixel.
    $this->assertStickyHeaderOffset(self::TABLE_ELEMENT, 1);
    $this->assertHeaderWidthsMatch(self::TABLE_ELEMENT);
  }

  /**
   * Tests the sticky header of a views table.
   *
   * @see \Drupal\views\Hook\ViewsThemeHooks::preprocessViewsViewTable()
   */
  public function testStickyHeaderOnViewsTable(): void {
    $this->drupalGet('admin/content');
    $assert_session = $this->assertSession();

    // The content view enables the sticky header, which makes the views table
    // template add both "sticky-enabled" and "position-sticky".
    $this->assertNotNull($assert_session->waitForElement('css', self::VIEWS_TABLE . '[data-once~="adminTableHeaderSticky"]'));

    $this->assertStickyHeaderRevealed();
    $this->assertProcessedMarker(self::VIEWS_TABLE);

    // The "sticky-enabled" class selects the second branch of the offset, which
    // pulls the clone seven pixels further up.
    $this->assertStickyHeaderOffset(self::VIEWS_TABLE, -7);
    $this->assertHeaderWidthsMatch(self::VIEWS_TABLE);
  }

  /**
   * Tests that the resize observer re-measures the clone.
   */
  public function testStickyHeaderFollowsResize(): void {
    $this->drupalGet('admin/content');
    $assert_session = $this->assertSession();
    $session = $this->getSession();

    $this->assertNotNull($assert_session->waitForElement('css', self::VIEWS_TABLE . '[data-once~="adminTableHeaderSticky"]'));
    $this->assertHeaderWidthsMatch(self::VIEWS_TABLE);

    // Record the width of the title column, which stretches with the table.
    $before = (float) $session->evaluateScript(sprintf(
      "document.querySelectorAll('%s thead th')[1].getBoundingClientRect().width",
      self::VIEWS_TABLE,
    ));
    $this->assertGreaterThan(0, $before);

    // Widening the window widens the table, because it takes the full width of
    // the content region.
    $session->resizeWindow(1600, 768);
    $this->assertJsCondition(sprintf(
      "Math.abs(document.querySelectorAll('%s thead th')[1].getBoundingClientRect().width - %.4F) > 1",
      self::VIEWS_TABLE,
      $before,
    ));

    // The observer of the real table re-runs the measurement, so both the
    // column widths and the vertical offset of the clone are written again.
    $this->assertHeaderWidthsMatch(self::VIEWS_TABLE);
    $this->assertStickyHeaderOffset(self::VIEWS_TABLE, -7);
  }

  /**
   * Tests that the clone mirrors the horizontal scroll of the real table.
   */
  public function testHorizontalScrollMirroring(): void {
    for ($index = 0; $index < self::EXTRA_ROLES; $index++) {
      Role::create([
        'id' => 'sticky_header_role_' . $index,
        'label' => 'Sticky header role ' . $index,
      ])->save();
    }

    $session = $this->getSession();
    // A narrow window plus the extra role columns guarantees that the table is
    // wider than its scroll container.
    $session->resizeWindow(640, 768);
    $this->drupalGet('admin/people/permissions');
    $assert_session = $this->assertSession();

    $this->assertNotNull($assert_session->waitForElement('css', self::TABLE_ELEMENT . '[data-once~="adminTableHeaderSticky"]'));
    $this->assertHeaderWidthsMatch(self::TABLE_ELEMENT);

    // Sizing the columns of the clone to the columns of the real table gives
    // both elements the same scrollable extent, which is what lets the ratio
    // based mirroring of the scroll library end up at identical offsets.
    $this->assertJsCondition(sprintf(
      "(() => { const wrapper = document.querySelector('%s'); const sticky = document.querySelector('%s'); const reach = wrapper.scrollWidth - wrapper.clientWidth; return reach > 0 && Math.abs(sticky.scrollWidth - sticky.clientWidth - reach) <= 1; })()",
      self::WRAPPER_SELECTOR,
      self::CLONE_SELECTOR,
    ));

    // Scrolling the container to its end pulls the clone along.
    $session->executeScript(sprintf(
      "document.querySelector('%s').scrollLeft = document.querySelector('%s').scrollWidth;",
      self::WRAPPER_SELECTOR,
      self::WRAPPER_SELECTOR,
    ));
    $this->assertJsCondition($this->scrollParityCondition('wrapper.scrollLeft > 0'));

    // An intermediate offset is mirrored just as well.
    $session->executeScript(sprintf("document.querySelector('%s').scrollLeft = 40;", self::WRAPPER_SELECTOR));
    $this->assertJsCondition($this->scrollParityCondition('Math.abs(wrapper.scrollLeft - 40) <= 1'));

    // And so is the way back to the start.
    $session->executeScript(sprintf("document.querySelector('%s').scrollLeft = 0;", self::WRAPPER_SELECTOR));
    $this->assertJsCondition($this->scrollParityCondition('wrapper.scrollLeft === 0'));

    // The scroll library registers both elements of the group, so dragging the
    // clone moves the real table too.
    $session->executeScript(sprintf("document.querySelector('%s').scrollLeft = 60;", self::CLONE_SELECTOR));
    $this->assertJsCondition($this->scrollParityCondition('Math.abs(sticky.scrollLeft - 60) <= 1'));
  }

  /**
   * Asserts that the script took the header clone out of its hidden state.
   */
  private function assertStickyHeaderRevealed(): void {
    // The clone is rendered with a "hidden" attribute and the stylesheet hides
    // it a second time with "visibility: hidden". The script drops the
    // attribute and overrides both the display and the visibility inline.
    $this->assertJsCondition(sprintf(
      "(() => { const sticky = document.querySelector('%s'); return sticky !== null && !sticky.hasAttribute('hidden') && sticky.style.display === 'block' && sticky.style.visibility === 'visible' && document.body.style.overflowX === 'hidden'; })()",
      self::CLONE_SELECTOR,
    ));

    // The combination of the three is what actually makes the clone visible.
    $this->assertNotNull($this->assertSession()->waitForElementVisible('css', self::CLONE_SELECTOR));
  }

  /**
   * Asserts that the script marked the observed table as processed.
   *
   * @param string $table_selector
   *   The selector of the observed table.
   */
  private function assertProcessedMarker(string $table_selector): void {
    // A class starting with two hyphens is not a valid selector for the CSS to
    // XPath translation Mink performs, so this is read in the browser.
    $this->assertJsCondition(sprintf(
      "document.querySelectorAll('%s.--is-processed').length === 1",
      $table_selector,
    ));
  }

  /**
   * Asserts the negative bottom margin the script writes on the clone.
   *
   * @param string $table_selector
   *   The selector of the observed table.
   * @param int $offset
   *   The offset the script adds to the measured header height.
   */
  private function assertStickyHeaderOffset(string $table_selector, int $offset): void {
    $this->assertJsCondition(sprintf(
      "(() => { const sticky = document.querySelector('%s'); const source = document.querySelector('%s'); return sticky !== null && source !== null && Math.abs(parseFloat(sticky.style.marginBottom) + source.querySelector('thead').getBoundingClientRect().height + (%d)) <= 0.5; })()",
      self::CLONE_SELECTOR,
      $table_selector,
      $offset,
    ));
  }

  /**
   * Asserts that every column of the clone is as wide as its counterpart.
   *
   * @param string $table_selector
   *   The selector of the observed table.
   */
  private function assertHeaderWidthsMatch(string $table_selector): void {
    // The script writes the measured width of each header cell of the real
    // table onto the matching cell of the clone, so the rendered widths have to
    // agree. Sub pixel layout makes an exact match unreachable, hence the one
    // pixel tolerance.
    $this->assertJsCondition(sprintf(
      "(() => { const cloned = document.querySelectorAll('%s thead th'); const source = document.querySelectorAll('%s thead th'); return cloned.length === source.length && cloned.length >= 3 && Array.prototype.every.call(cloned, (cell, index) => Math.abs(cell.getBoundingClientRect().width - source[index].getBoundingClientRect().width) <= 1); })()",
      self::CLONE_SELECTOR,
      $table_selector,
    ));
  }

  /**
   * Builds a condition asserting that both elements share a scroll offset.
   *
   * @param string $reached
   *   An additional expression describing the offset that has to be reached.
   *
   * @return string
   *   The condition to hand to ::assertJsCondition().
   */
  private function scrollParityCondition(string $reached): string {
    return sprintf(
      "(() => { const wrapper = document.querySelector('%s'); const sticky = document.querySelector('%s'); return (%s) && Math.abs(sticky.scrollLeft - wrapper.scrollLeft) <= 1; })()",
      self::WRAPPER_SELECTOR,
      self::CLONE_SELECTOR,
      $reached,
    );
  }

}
