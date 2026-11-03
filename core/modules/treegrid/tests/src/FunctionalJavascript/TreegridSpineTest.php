<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the treegrid spine JS against an in-module test consumer.
 *
 * Exercises the <drupal-treegrid> element upgrade, the roving-tabindex focus
 * model (arrow keys and click), the hierarchy exposed on the focused row
 * through aria-level/aria-posinset/aria-setsize, keyboard navigation, lazy
 * expand/collapse with its live-region announcement, and the client
 * cell-renderer registry — with no consuming module involved, so the spine
 * has its own coverage. Row windowing (IntersectionObserver /
 * content-visibility) is left out: it is nondeterministic under WebDriver.
 */
#[Group('treegrid')]
#[RunTestsInSeparateProcesses]
final class TreegridSpineTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'treegrid',
    'treegrid_test_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalGet('/treegrid-test-ui');
    $this->assertSession()->waitForElement(
      'css',
      'drupal-treegrid table[role="treegrid"] tbody tr[role="row"]',
    );
  }

  /**
   * The first top-level row is the only initial tab stop.
   */
  public function testInitialRovingTabStop(): void {
    $this->assertSame('0', $this->row('p1')->getAttribute('tabindex'));
    $this->assertSame('-1', $this->row('p2')->getAttribute('tabindex'));
    $this->assertSame('-1', $this->row('leaf1')->getAttribute('tabindex'));
  }

  /**
   * Activating a parent's disclosure lazy-loads its children at the right level.
   */
  public function testExpandLoadsChildren(): void {
    $this->expand('p1');

    $this->assertSame('true', $this->row('p1')->getAttribute('aria-expanded'));
    $children = $this->getSession()->getPage()->findAll('css', 'tr[role="row"][data-parent-id="p1"]');
    $this->assertCount(2, $children);
    $this->assertSame('2', $this->row('c1a')->getAttribute('aria-level'));
    $this->assertSame('2', $this->row('c1b')->getAttribute('aria-level'));
  }

  /**
   * Collapsing a parent hides its children and clears aria-expanded.
   */
  public function testCollapseHidesChildren(): void {
    $this->expand('p1');

    // Left on an expanded parent collapses it.
    $this->pressKeyOnRow('p1', 'ArrowLeft');
    $this->assertJsCondition(
      'document.querySelector(\'tr[data-id="p1"]\').getAttribute("aria-expanded") === "false"',
    );
    $this->assertSame('true', $this->row('c1a')->getAttribute('data-treegrid-hidden'));
  }

  /**
   * Arrow / Home / End navigation moves focus and the roving tab stop.
   */
  public function testKeyboardNavigation(): void {
    $this->pressKeyOnRow('p1', 'ArrowDown');
    $this->assertSame('p2', $this->activeRowId());
    $this->assertSame('0', $this->row('p2')->getAttribute('tabindex'));
    $this->assertSame('-1', $this->row('p1')->getAttribute('tabindex'));

    $this->pressKeyOnRow('p2', 'ArrowUp');
    $this->assertSame('p1', $this->activeRowId());

    $this->pressKeyOnRow('p1', 'End');
    $this->assertSame('leaf1', $this->activeRowId());

    $this->pressKeyOnRow('leaf1', 'Home');
    $this->assertSame('p1', $this->activeRowId());
  }

  /**
   * Loaded rows carry the cells the server rendered for them.
   *
   * The checkbox value and the mark cell differ by row, so the cells come from
   * the rows response rather than from client code.
   */
  public function testLoadedChildCellsComeFromServer(): void {
    $this->expand('p1');

    // c1a's checkbox value is FALSE (its ID contains "a"); c1b's is TRUE.
    $c1a_checkbox = $this->getSession()->getPage()->find(
      'css',
      'table[role="treegrid"] > tbody > tr[data-id="c1a"] input.treegrid-cell-checkbox',
    );
    $this->assertNotNull($c1a_checkbox, 'The loaded row has a checkbox cell.');
    $this->assertFalse($c1a_checkbox->isChecked());
    $this->assertTrue(
      $this->getSession()->getPage()
        ->find('css', 'table[role="treegrid"] > tbody > tr[data-id="c1b"] input.treegrid-cell-checkbox')
        ->isChecked(),
    );

    $markup = $this->getSession()->getPage()->find(
      'css',
      'tr[data-id="c1a"] td[data-column="mark"]',
    );
    $this->assertNotNull($markup, 'The loaded row has a mark cell.');
    $this->assertStringContainsString('m-c1a', $markup->getText());
  }

  /**
   * Clicking a row's content focuses it and moves the roving tab stop.
   */
  public function testClickToFocusRow(): void {
    $markup = $this->getSession()->getPage()->find(
      'css',
      'tr[role="row"][data-id="p2"] td[data-column="mark"]',
    );
    $this->assertNotNull($markup, 'Row p2 should render a markup cell.');
    $markup->click();

    $this->assertSame('p2', $this->activeRowId());
    $this->assertSame('0', $this->row('p2')->getAttribute('tabindex'));
    $this->assertSame('-1', $this->row('p1')->getAttribute('tabindex'));
  }

  /**
   * The focused row exposes its level, position, and sibling count.
   */
  public function testFocusedRowExposesHierarchy(): void {
    $this->pressKeyOnRow('p1', 'ArrowDown');
    $this->assertSame('p2', $this->activeRowId());

    $active = $this->row('p2');
    $this->assertSame('1', $active->getAttribute('aria-level'));
    $this->assertSame('2', $active->getAttribute('aria-posinset'));
    $this->assertSame('3', $active->getAttribute('aria-setsize'));
  }

  /**
   * Expanding a row announces its name through the live region.
   */
  public function testToggleAnnouncesRowName(): void {
    $this->pressKeyOnRow('p1', 'ArrowRight');

    $this->assertJsCondition(
      "((document.getElementById('drupal-live-announce') || {}).textContent || '').includes('Row p1')",
    );
  }

  /**
   * A row name holding markup is announced as text when toggled by keyboard.
   */
  public function testToggleAnnouncementEscapesRowNameFromKeyboard(): void {
    $this->setRowLabelToMarkup('p1');
    $this->pressKeyOnRow('p1', 'ArrowRight');
    $this->assertAnnouncementIsText();
  }

  /**
   * A row name holding markup is announced as text when toggled by click.
   */
  public function testToggleAnnouncementEscapesRowNameFromClick(): void {
    $this->setRowLabelToMarkup('p2');
    $this->expand('p2');
    $this->assertAnnouncementIsText();
  }

  /**
   * Child and sibling lookups ignore rows that are not items.
   */
  public function testSiblingAndChildHelpersSkipRowsWithoutId(): void {
    $this->expand('p1');
    $script = <<<'JS'
      const { childRows, nextSiblingRow } = Drupal.treegrid.treeNav;
      const p1 = document.querySelector('tr[data-id="p1"]');
      const p2 = document.querySelector('tr[data-id="p2"]');
      const c1b = document.querySelector('tr[data-id="c1b"]');
      const make = (level) => {
        const tr = document.createElement('tr');
        tr.setAttribute('role', 'row');
        tr.setAttribute('aria-level', String(level));
        tr.dataset.parentId = level === 1 ? '' : 'p1';
        return tr;
      };
      p1.after(make(2));
      c1b.after(make(1));
      return {
        children: childRows(p1).map((row) => row.dataset.id),
        next: nextSiblingRow(p1)?.dataset.id ?? null,
        expectedNext: p2.dataset.id,
      };
    JS;
    $result = $this->getSession()->evaluateScript('return (function () {' . $script . '})();');
    $this->assertSame(['c1a', 'c1b'], $result['children']);
    $this->assertSame($result['expectedNext'], $result['next']);
  }

  // Helpers.

  /**
   * Gives a row an aria-label that is markup if it were read as HTML.
   *
   * @param string $id
   *   The row ID.
   */
  private function setRowLabelToMarkup(string $id): void {
    $this->getSession()->executeScript(<<<JS
      document.querySelector('tr[role="row"][data-id="$id"]').setAttribute(
        'aria-label',
        '<img src=x onerror="window.__xss=1">',
      );
    JS);
  }

  /**
   * Asserts the live region holds the announcement as text, not as markup.
   */
  private function assertAnnouncementIsText(): void {
    $this->assertJsCondition(
      "((document.getElementById('drupal-live-announce') || {}).innerHTML || '') !== ''",
    );
    // An onerror handler fires asynchronously, so give it the chance to run.
    usleep(300000);
    $this->assertNull($this->getSession()->evaluateScript('return window.__xss ?? null;'));
    $this->assertSame(0, $this->getSession()->evaluateScript(
      "return document.getElementById('drupal-live-announce').children.length;",
    ));
  }

  /**
   * Finds a row by its data-id.
   *
   * @param string $id
   *   The row ID.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The row element.
   */
  private function row(string $id): NodeElement {
    $row = $this->getSession()->getPage()->find('css', sprintf('tr[role="row"][data-id="%s"]', $id));
    $this->assertNotNull($row, "Row $id should be present.");
    return $row;
  }

  /**
   * Clicks a parent row's disclosure toggle and waits for its children.
   *
   * @param string $id
   *   The parent row ID.
   */
  private function expand(string $id): void {
    $toggle = $this->getSession()->getPage()->find(
      'css',
      sprintf('tr[role="row"][data-id="%s"] button[data-treegrid-toggle]', $id),
    );
    $this->assertNotNull($toggle, "Row $id should have a disclosure toggle.");
    $toggle->click();
    $this->assertSession()->waitForElement(
      'css',
      sprintf('tr[role="row"][data-parent-id="%s"]', $id),
    );
  }

  /**
   * Focuses a row and dispatches a keydown on it.
   *
   * @param string $id
   *   The row ID.
   * @param string $key
   *   The KeyboardEvent.key value to dispatch.
   */
  private function pressKeyOnRow(string $id, string $key): void {
    $key_json = json_encode($key);
    $script = <<<JS
      const row = document.querySelector('tr[role="row"][data-id="$id"]');
      if (!row) throw new Error('row $id not in DOM');
      row.scrollIntoView({block: 'center'});
      row.focus();
      row.dispatchEvent(new KeyboardEvent('keydown', {
        key: $key_json, bubbles: true, cancelable: true,
      }));
    JS;
    $this->getSession()->executeScript($script);
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
