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
   * Reorders a row among its siblings, persisting via the move POST.
   *
   * @param {HTMLElement} row
   *   The row to move.
   * @param {('up'|'down')} direction
   *   Which way to move it.
   * @param {object} options
   *   { moveUrl, csrfToken, flashClass } — the move endpoint, the X-CSRF-Token
   *   value (the move route requires it), and the consumer's brief-flash class.
   *
   * @return {Promise<boolean>}
   *   True if the handler took the key (so the caller preventDefaults).
   */
  async function reorderSibling(row, direction, options) {
    const { moveUrl, csrfToken, flashClass } = options;
    const movedId = row.dataset.id;
    if (!movedId || row.dataset.movable !== 'true') {
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
    };

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
      if (!response.ok) {
        restore();
        announce(Drupal.t('Move failed — restored.'), 'assertive');
      }
    } catch (e) {
      restore();
      // eslint-disable-next-line no-console
      console.error('[treegrid-reorder] reorder failed', e);
    }
    return true;
  }

  Drupal.treegridReorder.reorderSibling = reorderSibling;
})(Drupal);
