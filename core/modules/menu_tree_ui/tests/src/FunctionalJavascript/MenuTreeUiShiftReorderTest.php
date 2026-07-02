<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the menu_tree_ui Shift+Arrow sibling reorder end-to-end.
 *
 * Shift+Up/Down persists a same-parent sibling swap through the same move
 * endpoint as drag and keyboard move mode (see reorder-keys.js), but is
 * driven directly at the row rather than through the grab/select move mode.
 * This covers the fix where a successful reorder never fired the working
 * copy's onCommitted callback, so the unsaved-changes notice stayed hidden
 * even though the server had already recorded the move.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiShiftReorderTest extends MenuTreeUiJavascriptTestBase {

  /**
   * Seeds two top-level siblings, A and B.
   */
  protected function seedMenu(): void {
    $this->createTestMenu();
    $this->linkIds['A'] = $this->createLink('A', '', 0);
    $this->linkIds['B'] = $this->createLink('B', '', 1);
  }

  /**
   * Shift+ArrowDown on A swaps it past B and reveals the unsaved notice.
   */
  public function testShiftArrowDownRevealsUnsavedNotice(): void {
    $notice = $this->getSession()->getPage()->find('css', '.menu-tree-ui__unsaved');
    $this->assertNotNull($notice, 'The unsaved notice element should be present.');
    $this->assertTrue(
      $notice->hasClass('js-hide'),
      'The unsaved notice should start hidden (js-hide).',
    );

    $this->dispatchShiftArrow('A', 'ArrowDown');

    // The optimistic swap happens synchronously; the notice only appears once
    // the move POST round-trips and fires onCommitted, so wait for it rather
    // than asserting immediately after the DOM order changes.
    $this->assertJsCondition(
      "!document.querySelector('.menu-tree-ui__unsaved').classList.contains('js-hide')",
    );
    $this->assertSiblings(['B', 'A'], $this->childRowsSelector(NULL));

    // The swap should renumber both rows' aria-posinset to their new slots.
    $this->assertSame(
      '1',
      $this->row('B')->getAttribute('aria-posinset'),
      'B should report position 1 after moving ahead of A.',
    );
    $this->assertSame(
      '2',
      $this->row('A')->getAttribute('aria-posinset'),
      'A should report position 2 after B passed it.',
    );
  }

  /**
   * Focuses $label and dispatches a Shift+$key keydown at it.
   *
   * MenuTreeUiKeyboardMoveTrait::dispatchKeys() sends unmodified keys only
   * (move mode's own keys are never chorded), so Shift+Arrow needs its own
   * sender here.
   */
  private function dispatchShiftArrow(string $label, string $key): void {
    $id = $this->linkIds[$label] ?? throw new \InvalidArgumentException("Unknown label: $label");
    $script = <<<JS
      const row = document.querySelector('tr[role="row"][data-id="$id"]');
      if (!row) throw new Error('row not in DOM');
      row.tabIndex = 0;
      row.focus();
      row.dispatchEvent(new KeyboardEvent('keydown', {
        key: '$key', shiftKey: true, bubbles: true, cancelable: true,
      }));
    JS;
    $this->getSession()->executeScript($script);
  }

}
