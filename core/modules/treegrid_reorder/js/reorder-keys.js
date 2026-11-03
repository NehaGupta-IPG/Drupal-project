/**
 * @file
 * Shift+Arrow sibling reorder.
 *
 * Same server endpoint as drag (the move POST). Same optimistic-then-revert
 * shape. Kept in its own file because it spans DOM mutation + fetch + announce,
 * which doesn't really belong in the navigation keyboard handler.
 *
 * A row carries its descendant run as a block, so reordering moves the whole
 * subtree past the previous/next sibling block as a unit.
 *
 * Exposes Drupal.treegridReorder.reorderSibling and previousSiblingRow.
 */

((Drupal) => {
  Drupal.treegridReorder = Drupal.treegridReorder || {};
  const { descendantRows, lastDescendantRow, nextSiblingRow } =
    Drupal.treegrid.treeNav;
  const { announce } = Drupal.treegrid;
  const {
    rowTitle,
    dispatchMoveEvent,
    failureMessage,
    reportFailure,
    refuseMove,
    unsentResult,
  } = Drupal.treegridReorder;

  const ROW = 'tr[role="row"]';
  const FLASH = 'data-treegrid-reorder-flash';
  const FLASH_FALLBACK_MS = 2000;

  /**
   * The previous sibling row at the same level under the same parent.
   *
   * @param {HTMLElement} row
   *   The reference row.
   *
   * @return {?HTMLElement}
   *   The previous sibling row, or null when the row is first.
   */
  function previousSiblingRow(row) {
    let prev = row.previousElementSibling;
    while (prev && prev.matches(ROW)) {
      if (prev.dataset.id && prev.dataset.parentId === row.dataset.parentId) {
        return prev;
      }
      prev = prev.previousElementSibling;
    }
    return null;
  }

  /**
   * Renumbers aria-posinset across a row's sibling group.
   *
   * The optimistic swap moves the row in the DOM ahead of the server's
   * response, which leaves the affected siblings' positions stale (screen
   * readers announce "item N of M" from these, not document order). A swap
   * keeps the number of siblings, so aria-setsize stays as it is: with a level
   * only partly loaded the loaded rows are not the level's size. Only item rows
   * (those with a data-id) are numbered. Scoped to the row's own table so an
   * unrelated tree on the page can't be touched.
   *
   * @param {HTMLElement} row
   *   Any row in the affected sibling group.
   */
  function renumberSiblings(row) {
    const tree = row.closest('table[role="treegrid"]');
    if (!tree) {
      return;
    }
    const parentId = row.dataset.parentId ?? '';
    const siblings = Array.from(
      tree.querySelectorAll(
        `${ROW}[data-parent-id="${CSS.escape(parentId)}"][data-id]`,
      ),
    );
    siblings.forEach((sibling, index) => {
      sibling.setAttribute('aria-posinset', String(index + 1));
    });
  }

  /**
   * The translated "moved" announcement for a completed reorder.
   *
   * Built as two full sentences (one per direction) rather than concatenating
   * translated fragments, so each is translatable on its own.
   *
   * @param {string} title
   *   The moved row's title.
   * @param {('up'|'down')} direction
   *   Which way it moved.
   *
   * @return {string}
   *   The translated confirmation sentence.
   */
  function movedMessage(title, direction) {
    if (direction === 'up') {
      return Drupal.t('@title moved up.', { '@title': title });
    }
    return Drupal.t('@title moved down.', { '@title': title });
  }

  /**
   * Flashes a row to show where it went.
   *
   * The flash state ends with the animation the module's stylesheet draws. A
   * fallback timer ends it too, because with reduced motion there is no
   * animation and so no animationend.
   *
   * @param {HTMLElement} row
   *   The row to flash.
   */
  function flash(row) {
    row.setAttribute(FLASH, 'true');
    const end = (event) => {
      if (event && event.target !== row) {
        return;
      }
      window.clearTimeout(timer);
      row.removeEventListener('animationend', end);
      row.removeAttribute(FLASH);
    };
    const timer = window.setTimeout(end, FLASH_FALLBACK_MS);
    row.addEventListener('animationend', end);
  }

  /**
   * Swaps a row with its sibling inside a running unit and persists the swap.
   *
   * The optimistic swap happens when the unit starts, so it never lands in the
   * middle of a page load or refresh. A failed move puts the rows back.
   *
   * @param {object} unit
   *   The unit the move runs in.
   * @param {HTMLElement} row
   *   The row to move.
   * @param {('up'|'down')} direction
   *   Which way to move it.
   * @param {object} context
   *   The move context: { element, moveUrl() }.
   *
   * @return {Promise<void>}
   *   Resolves once the move is recorded or put back.
   */
  async function runSwap(unit, row, direction, context) {
    const { element } = context;
    const movedId = row.dataset.id;
    const parentId = row.dataset.parentId ?? '';
    // The row can be gone, or have lost its sibling, by the time the unit
    // starts, for instance after a refresh that ran ahead of it.
    let sibling = null;
    if (row.isConnected) {
      sibling =
        direction === 'up' ? previousSiblingRow(row) : nextSiblingRow(row);
    }
    if (!sibling) {
      reportFailure(
        element,
        { id: movedId, parentId, beforeId: null, gesture: 'shift-arrow' },
        unsentResult('unavailable'),
        Drupal.t('That item can no longer move there.'),
      );
      return;
    }

    const block = descendantRows(row);
    // Snapshot the original position so a failed move can restore it: the row
    // currently sits before this node.
    const oldNext = (lastDescendantRow(row) ?? row).nextElementSibling;

    // Optimistic swap: move the row (and its block) past the sibling block.
    const insert = (before) => {
      const parent = row.parentNode;
      let anchor = row;
      parent.insertBefore(row, before);
      block.forEach((r) => {
        anchor.after(r);
        anchor = r;
      });
    };
    if (direction === 'up') {
      insert(sibling);
    } else {
      const siblingEnd = (lastDescendantRow(sibling) ?? sibling)
        .nextElementSibling;
      insert(siblingEnd);
    }
    renumberSiblings(row);

    const beforeId = nextSiblingRow(row)?.dataset.id ?? null;

    // Pull focus to the row only if it is still in the tree, so an editor who
    // has moved on keeps their place.
    const { tree } = element;
    if (
      document.activeElement === document.body ||
      tree.contains(document.activeElement)
    ) {
      row.focus();
    }
    flash(row);

    const result = await Drupal.treegridReorder.request(
      element,
      context.moveUrl(),
      {
        body: { id: movedId, new_parent: parentId, before_id: beforeId },
        unit,
      },
    );
    if (!result.ok) {
      let anchor = row;
      row.parentNode.insertBefore(row, oldNext);
      block.forEach((r) => {
        anchor.after(r);
        anchor = r;
      });
      renumberSiblings(row);
      reportFailure(
        element,
        { id: movedId, parentId, beforeId, gesture: 'shift-arrow' },
        result,
        failureMessage(
          result,
          Drupal.t('Move failed. Order restored.'),
          Drupal.t('Network error. Order restored.'),
        ),
      );
      return;
    }
    announce(movedMessage(rowTitle(row), direction), 'polite');
    dispatchMoveEvent(element, 'treegrid-reorder:moved', {
      id: movedId,
      parentId,
      beforeId,
      previousParentId: parentId,
      gesture: 'shift-arrow',
      refreshed: true,
      result,
    });
  }

  /**
   * Reorders a row among its siblings, persisting via the move POST.
   *
   * @param {HTMLElement} row
   *   The row to move.
   * @param {('up'|'down')} direction
   *   Which way to move it.
   * @param {object} context
   *   The move context: { element, moveUrl() }.
   *
   * @return {Promise<boolean>}
   *   True if the handler took the key (so the caller preventDefaults).
   */
  async function reorderSibling(row, direction, context) {
    if (!context.moveUrl()) {
      console.warn('[treegrid-reorder] no move URL — move ignored.');
      return false;
    }
    if (!row.dataset.id || row.dataset.movable !== 'true') {
      return false;
    }
    const sibling =
      direction === 'up' ? previousSiblingRow(row) : nextSiblingRow(row);
    if (!sibling) {
      return false;
    }

    // One move at a time: a key pressed while another move or a write is in
    // the way is refused, not queued, since its swap would be worked out from a
    // screen that is about to change.
    const { element } = context;
    if (!Drupal.treegridReorder.acceptMove(element)) {
      refuseMove(element, {
        id: row.dataset.id,
        parentId: row.dataset.parentId ?? '',
        beforeId: null,
        gesture: 'shift-arrow',
      });
      return true;
    }
    try {
      await element.exclusive((unit) => runSwap(unit, row, direction, context));
    } finally {
      Drupal.treegridReorder.finishMove(element);
    }
    return true;
  }

  Drupal.treegridReorder.reorderSibling = reorderSibling;
  Drupal.treegridReorder.previousSiblingRow = previousSiblingRow;
})(Drupal);
