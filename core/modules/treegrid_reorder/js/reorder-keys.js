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
      if (prev.dataset.parentId === row.dataset.parentId) {
        return prev;
      }
      prev = prev.previousElementSibling;
    }
    return null;
  }

  /**
   * Renumbers aria-posinset/aria-setsize across a row's sibling group.
   *
   * The optimistic swap moves the row in the DOM ahead of the server's
   * response, which leaves the affected siblings' position attributes stale
   * (screen readers announce "item N of M" from these, not document order).
   * Scoped to the row's own table so an unrelated tree on the page (there
   * should only ever be one) can't be touched.
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
      tree.querySelectorAll(`${ROW}[data-parent-id="${CSS.escape(parentId)}"]`),
    );
    siblings.forEach((sibling, index) => {
      sibling.setAttribute('aria-posinset', String(index + 1));
      sibling.setAttribute('aria-setsize', String(siblings.length));
    });
  }

  /**
   * Reads a row's own title text.
   *
   * @param {HTMLElement} row
   *   The row.
   * @param {string} titleSelector
   *   The consumer's selector for a row's title.
   *
   * @return {string}
   *   The trimmed title, or ''.
   */
  function titleOf(row, titleSelector) {
    return row.querySelector(titleSelector)?.textContent?.trim() ?? '';
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
   * Reorders a row among its siblings, persisting via the move POST.
   *
   * @param {HTMLElement} row
   *   The row to move.
   * @param {('up'|'down')} direction
   *   Which way to move it.
   * @param {object} options
   *   { moveUrl, csrfToken, flashClass, onCommitted, titleSelector } — the move
   *   endpoint, the X-CSRF-Token value (the move route requires it), the
   *   consumer's brief-flash class, the callback fired after a persisted move
   *   (reveals the unsaved-changes notice), and the consumer's row-title
   *   selector (for the announcement).
   *
   * @return {Promise<boolean>}
   *   True if the handler took the key (so the caller preventDefaults).
   */
  async function reorderSibling(row, direction, options) {
    const { moveUrl, csrfToken, flashClass, onCommitted, titleSelector } =
      options;
    const movedId = row.dataset.id;
    if (!movedId || row.dataset.movable !== 'true') {
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
    row.classList.add(flashClass);
    window.setTimeout(() => {
      row.classList.remove(flashClass);
    }, 600);

    const restore = () => {
      let anchor = row;
      row.parentNode.insertBefore(row, oldNext);
      block.forEach((r) => {
        anchor.after(r);
        anchor = r;
      });
      renumberSiblings(row);
    };

    movesInFlight.add(row);
    try {
      const response = await fetch(moveUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-Token': csrfToken ?? '',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          id: movedId,
          new_parent: parentId,
          before_id: beforeId,
        }),
      });
      const body = await response.json().catch(() => ({ ok: false }));
      if (!response.ok || !body.ok) {
        restore();
        announce(Drupal.t('Move failed. Order restored.'), 'assertive');
      } else {
        onCommitted?.();
        announce(
          movedMessage(titleOf(row, titleSelector), direction),
          'polite',
        );
      }
    } catch (e) {
      restore();
      announce(Drupal.t('Network error. Order restored.'), 'assertive');
      // eslint-disable-next-line no-console
      console.error('[treegrid-reorder] reorder failed', e);
    } finally {
      movesInFlight.delete(row);
    }
    return true;
  }

  Drupal.treegridReorder.reorderSibling = reorderSibling;
})(Drupal);
