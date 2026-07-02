<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Regression tests for DragController client-side gesture guards.
 *
 * Covers three hardening fixes in treegrid_reorder's DragController:
 *  - A gesture that crosses the drag threshold but ends up with no valid
 *    intent (e.g. released over the moved row's own subtree) must not fall
 *    through to the tap path and pick the row up into keyboard move mode.
 *  - Dropping a parent onto one of its own descendants must never form a
 *    drop intent client-side (previously relied on a POST + server
 *    rejection to block it).
 *  - A second concurrent pointer (e.g. multi-touch) must be ignored while a
 *    gesture is already in progress, rather than clobbering the first.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiDragGuardsTest extends MenuTreeUiJavascriptTestBase {

  use MenuTreeUiDragGestureTrait;

  /**
   * Seeds a small tree: P (parent of C), Q (leaf), R (leaf).
   */
  protected function seedMenu(): void {
    $this->createTestMenu();
    $weight = 0;
    foreach (['P', 'Q', 'R'] as $label) {
      $this->linkIds[$label] = $this->createLink($label, '', $weight++);
    }
    $this->linkIds['C'] = $this->createLink('C', $this->linkIds['P'], 0);
  }

  /**
   * Dropping P onto its own child C must not form an intent or commit a move.
   *
   * Covers the descendant-drop guard in updateIntent(): C is a descendant of
   * P, so no intent should ever form. Since the release lands mid-drag with
   * no valid intent, this also covers the tap-routing fix in onPointerUp():
   * a cancelled drag must not fall through to the tap path and pick P up
   * into keyboard move mode.
   */
  public function testCannotDropParentOntoOwnChild(): void {
    $this->expandRow('P');
    $this->drag('P', 'C', 'after');

    $this->assertSiblings(['P', 'Q', 'R'], $this->childRowsSelector(NULL));
    $this->assertSiblings(['C'], $this->childRowsSelector('P'));

    $grabbed = $this->getSession()->evaluateScript(
      "return document.querySelector('.menu-tree-ui-row--grabbed') !== null;",
    );
    $this->assertFalse(
      $grabbed,
      'A drag cancelled over an invalid target must not pick the row up into move mode.',
    );
  }

  /**
   * A second concurrent pointer must be ignored, not clobber the first.
   *
   * Starts a real drag with pointer 1 on P's handle, then fires a second
   * pointerdown with pointer 2 on Q's handle mid-gesture. The guard at the
   * top of onPointerDown should return before Q's handle takes pointer
   * capture, and pointer 1's gesture should complete normally afterwards
   * (dropping P after R).
   */
  public function testConcurrentPointerIgnored(): void {
    $p_id = $this->linkIds['P'];
    $q_id = $this->linkIds['Q'];
    $r_id = $this->linkIds['R'];

    $script = <<<JS
      (() => {
        const pRow = document.querySelector('tr[role="row"][data-id="$p_id"]');
        const qRow = document.querySelector('tr[role="row"][data-id="$q_id"]');
        const rRow = document.querySelector('tr[role="row"][data-id="$r_id"]');
        rRow.scrollIntoView({block: 'center'});
        const pHandle = pRow.querySelector('.menu-tree-ui-row__handle:not(.menu-tree-ui-row__handle--locked)');
        const qHandle = qRow.querySelector('.menu-tree-ui-row__handle:not(.menu-tree-ui-row__handle--locked)');
        const pRect = pHandle.getBoundingClientRect();
        const qRect = qHandle.getBoundingClientRect();
        const rRect = rRow.getBoundingClientRect();
        const startX = pRect.left + pRect.width / 2;
        const startY = pRect.top + pRect.height / 2;
        const qX = qRect.left + qRect.width / 2;
        const qY = qRect.top + qRect.height / 2;
        const endX = rRect.left + rRect.width / 2;
        const endY = rRect.top + rRect.height * 0.9;
        function send(t, type, x, y, pointerId) {
          t.dispatchEvent(new PointerEvent(type, {
            bubbles: true, cancelable: true, composed: true,
            pointerId, isPrimary: pointerId === 1, button: 0,
            clientX: x, clientY: y, screenX: x, screenY: y,
          }));
        }
        send(pHandle, 'pointerdown', startX, startY, 1);
        send(qHandle, 'pointerdown', qX, qY, 2);
        const qCaptured = qHandle.hasPointerCapture(2);
        send(pHandle, 'pointermove', startX + 10, startY + 10, 1);
        send(pHandle, 'pointermove', endX, endY, 1);
        send(pHandle, 'pointerup', endX, endY, 1);
        return qCaptured;
      })();
    JS;

    $q_captured = $this->getSession()->evaluateScript($script);
    $this->assertFalse(
      $q_captured,
      'A second concurrent pointerdown must be ignored and never take pointer capture.',
    );

    $this->getSession()->wait(
      5000,
      '!document.querySelector(\'drupal-treegrid[data-treegrid-moving="true"]\')'
      . ' && !document.querySelector(\'.menu-tree-ui-row--dragging\')'
      . ' && !document.querySelector(\'[data-loading="true"]\')',
    );
    $this->assertSiblings(['Q', 'R', 'P'], $this->childRowsSelector(NULL));
  }

}
