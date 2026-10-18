<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the menu_tree_ui working-copy Save and Cancel flows end-to-end.
 *
 * A drag or keyboard move records the move in a per-user server-side working
 * copy and overlays it on the rendered tree. The change is only persisted
 * when the user clicks Save; Cancel discards it.
 * These tests drive one drag, then assert the unsaved notice, and that Save
 * and Cancel each leave the expected order on screen. Committing and
 * discarding the working copy against storage is covered by the kernel
 * MenuTreeWorkingCopyTest.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiSaveCancelTest extends MenuTreeUiJavascriptTestBase {

  use MenuTreeUiDragGestureTrait;

  /**
   * Selector for an error message in the action bar's save-error container.
   */
  private const SAVE_ERROR_SELECTOR = '.menu-tree-ui__save-error [data-drupal-message-type="error"]';

  /**
   * Seeds a menu with three top-level links A, B, C (A has one child, A1).
   *
   * A1 lets a test expand A and check that a Save keeps it open.
   */
  protected function seedMenu(): void {
    $this->createTestMenu();

    $weight = 0;
    foreach (['A', 'B', 'C'] as $label) {
      $this->linkIds[$label] = $this->createLink($label, '', $weight++);
    }
    $this->linkIds['A1'] = $this->createLink('A1', $this->linkIds['A'], 0);
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
   * Save commits the working copy: the new order stays on screen, no reload.
   *
   * Persisting the reordered weights is covered by the kernel
   * MenuTreeWorkingCopyTest::testCommitAppliesMoves.
   */
  public function testSaveCommitsWorkingCopy(): void {
    $this->moveCBeforeA();

    $this->getSession()->getPage()->find('css', '.menu-tree-ui__save')->click();
    // Save does not reload the page; the unsaved notice hiding again signals
    // that the working copy was committed.
    $this->waitForSave();

    $this->assertSiblings(['C', 'A', 'B'], $this->childRowsSelector(NULL));
    $this->assertNull($this->getSession()->getPage()->find('css', self::SAVE_ERROR_SELECTOR));
  }

  /**
   * A Save that fails shows the error and keeps the unsaved notice.
   *
   * C is recorded as a child of A, then A is made a child of C behind the
   * editor's back, so the recorded move would now form a cycle.
   */
  public function testFailedSaveShowsErrorAndKeepsWorkingCopy(): void {
    $this->drag('C', 'A', 'child');
    $this->assertJsCondition(
      "!document.querySelector('.menu-tree-ui__unsaved').classList.contains('js-hide')",
    );
    $this->container->get('plugin.manager.menu.link')->updateDefinition(
      $this->linkIds['A'],
      ['parent' => $this->linkIds['C']],
    );

    $this->getSession()->getPage()->find('css', '[data-mtu-action="save"]')->click();

    $this->assertNotNull($this->assertSession()->waitForElementVisible('css', self::SAVE_ERROR_SELECTOR));
    $this->assertFalse(
      $this->getSession()->getPage()->find('css', '.menu-tree-ui__unsaved')->hasClass('js-hide'),
      'The unsaved notice should stay visible after a failed Save.',
    );
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
   * Waits for a Save to commit, signaled by the unsaved notice hiding again.
   */
  private function waitForSave(): void {
    $this->assertJsCondition(
      "document.querySelector('.menu-tree-ui__unsaved').classList.contains('js-hide')",
    );
  }

  /**
   * Cancel discards the pending reorder: the reload shows the seed order.
   *
   * Discarding the working copy is covered by the kernel
   * MenuTreeWorkingCopyTest::testRecordAndClear.
   */
  public function testCancelDiscardsMoves(): void {
    $this->moveCBeforeA();

    $this->getSession()->executeScript('window.__mtuReload = true;');
    $this->getSession()->getPage()->find('css', '.menu-tree-ui__cancel')->click();
    $this->waitForReload();

    $this->assertSiblings(['A', 'B', 'C'], $this->childRowsSelector(NULL));
  }

  /**
   * Drags row C into the "before" zone of row A and waits for the commit.
   *
   * The drag() helper waits for the host to clear its moving/loading flags,
   * and the waited assertSiblings() then polls for the reordered top level.
   * Waiting on the end-state order matters: the cleared flags can read true
   * before the reorder lands, racing a slower commit on CI.
   */
  private function moveCBeforeA(): void {
    $this->drag('C', 'A', 'before');
    $this->assertSiblings(['C', 'A', 'B'], $this->childRowsSelector(NULL));
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
      'drupal-treegrid table[role="treegrid"] tbody tr[role="row"]',
    );
  }

}
