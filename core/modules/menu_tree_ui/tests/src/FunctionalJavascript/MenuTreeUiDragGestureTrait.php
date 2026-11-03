<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

/**
 * Synthetic pointer-drag drivers for menu_tree_ui tests.
 *
 * Fires PointerEvent sequences at the live DragController. Expects the using
 * class to extend MenuTreeUiJavascriptTestBase (for $linkIds and the tree
 * helpers).
 */
trait MenuTreeUiDragGestureTrait {

  /**
   * Drags the row labeled $source into the $zone of the row labeled $target.
   *
   * Zones map to vertical position inside the target row's rect (the <tr> holds
   * only its own cells, never its subtree, so the row rect IS the header):
   *  - 'before' → 10% from top (top zone)
   *  - 'child'  → 50% (middle zone)
   *  - 'after'  → 90% (bottom zone)
   *
   * The 5px drag threshold in DragController is satisfied by an intermediate
   * pointermove offset before moving to the target.
   */
  private function drag(string $source_label, string $target_label, string $zone): void {
    $source_id = $this->linkIds[$source_label] ?? throw new \InvalidArgumentException("Unknown source label: $source_label");
    $target_id = $this->linkIds[$target_label] ?? throw new \InvalidArgumentException("Unknown target label: $target_label");
    $zone_ratio = match ($zone) {
      'before' => 0.1,
      'child'  => 0.5,
      'after'  => 0.9,
      default  => throw new \InvalidArgumentException("Unknown zone: $zone"),
    };

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
      const endY = tRect.top + tRect.height * $zone_ratio;
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
    // commitMove flags the <drupal-treegrid> host with data-treegrid-reorder-moving
    // while the server-first POST + group refresh is in flight; wait for that to
    // clear before assertions run on the rebuilt DOM.
    $this->getSession()->wait(
      5000,
      '!document.querySelector(\'drupal-treegrid[data-treegrid-reorder-moving="true"]\')'
      . ' && !document.querySelector(\'[data-treegrid-reorder-dragging]\')'
      . ' && !document.querySelector(\'[data-treegrid-loading]\')',
    );
  }

  /**
   * Clicks a destination row in the given zone to aim the active move at it.
   *
   * @param string $label
   *   The destination row label.
   * @param string $zone
   *   Where in the row to click: 'before' (top), 'child' (middle) or 'after'
   *   (bottom).
   */
  private function aimAtRow(string $label, string $zone): void {
    $id = $this->linkIds[$label] ?? throw new \InvalidArgumentException("Unknown label: $label");
    $ratio = match ($zone) {
      'before' => 0.1,
      'child'  => 0.5,
      'after'  => 0.9,
      default  => throw new \InvalidArgumentException("Unknown zone: $zone"),
    };
    $script = <<<JS
      const row = document.querySelector('tr[role="row"][data-id="$id"]');
      if (!row) throw new Error('destination row not in DOM');
      row.scrollIntoView({block: 'center'});
      const rect = row.getBoundingClientRect();
      const x = rect.left + rect.width / 2;
      const y = rect.top + rect.height * $ratio;
      row.dispatchEvent(new MouseEvent('click', {
        bubbles: true, cancelable: true, composed: true,
        clientX: x, clientY: y, button: 0,
      }));
    JS;
    $this->getSession()->executeScript($script);
  }

}
