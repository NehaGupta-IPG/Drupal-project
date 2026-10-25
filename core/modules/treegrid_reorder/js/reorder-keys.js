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
 * Exposes Drupal.treegridReorder.reorderSibling.
 */

((Drupal) => {
  Drupal.treegridReorder = Drupal.treegridReorder || {};
  const { descendantRows, lastDescendantRow, nextSiblingRow } =
    Drupal.treegrid.treeNav;
  const { announce } = Drupal.treegrid;
  const { rowTitle, dispatchMoveEvent, failureMessage, reportFailure } =
    Drupal.treegridReorder;

  const ROW = 'tr[role="row"]';

  // Rows with a Shift+Arrow move currently round-tripping to the server.
  // Guards against mashing the key firing overlapping optimistic swaps + POSTs,
  // where a later failure would restore against a stale captured position.
  const movesInFlight = new WeakSet();

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
    const sibling =
      direction === 'up' ? previousSiblingRow(row) : nextSiblingRow(row);
    if (!sibling) {
      return;
    }

    const movedId = row.dataset.id;
    const parentId = row.dataset.parentId ?? '';
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

    row.focus();
    row.setAttribute('data-treegrid-reorder-flash', 'true');
    window.setTimeout(() => {
      row.removeAttribute('data-treegrid-reorder-flash');
    }, 600);

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
      // eslint-disable-next-line no-console
      console.warn('[treegrid-reorder] no move URL — move ignored.');
      return false;
    }
    if (!row.dataset.id || row.dataset.movable !== 'true') {
      return false;
    }
    if (movesInFlight.has(row)) {
      // A previous Shift+Arrow on this row is still round-tripping; ignore the
      // new key rather than stacking optimistic swaps on an unsettled DOM.
      return false;
    }
    const sibling =
      direction === 'up' ? previousSiblingRow(row) : nextSiblingRow(row);
    if (!sibling) {
      return false;
    }

    movesInFlight.add(row);
    try {
      await context.element.exclusive((unit) =>
        runSwap(unit, row, direction, context),
      );
    } finally {
      movesInFlight.delete(row);
    }
    return true;
  }

  Drupal.treegridReorder.reorderSibling = reorderSibling;
})(Drupal);
