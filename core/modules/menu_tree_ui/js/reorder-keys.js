/**
 * @file
 * Shift+Arrow sibling reorder.
 *
 * Same server endpoint as drag (POST /move). Same optimistic-then-revert
 * shape. Kept in its own module because it spans DOM mutation + fetch +
 * announce, which doesn't really belong in keyboard.js.
 *
 * A row carries its descendant run as a block, so reordering moves the whole
 * subtree past the previous/next sibling block as a unit.
 *
 * Exposes Drupal.menuTreeUi.reorderSibling.
 */

((Drupal) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};
  const { descendantRows, lastDescendantRow, nextSiblingRow, handleOf } =
    Drupal.menuTreeUi.treeNav;
  const { announce } = Drupal.menuTreeUi;

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
   * Reorders a row among its siblings, persisting via POST /move.
   *
   * @param {HTMLElement} row
   *   The row to move.
   * @param {('up'|'down')} direction
   *   Which way to move it.
   * @param {string} moveUrl
   *   The move endpoint.
   * @param {string} [csrfToken]
   *   The X-CSRF-Token value; the move route requires it, so the same token
   *   the drag-commit path uses is sent here too.
   *
   * @return {Promise<boolean>}
   *   True if the handler took the key (so the caller preventDefaults).
   */
  async function reorderSibling(row, direction, moveUrl, csrfToken) {
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

    handleOf(row)?.focus();
    row.classList.add('menu-tree-ui-row--flash');
    window.setTimeout(() => {
      row.classList.remove('menu-tree-ui-row--flash');
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
        announce('Move failed — restored.', 'assertive');
      }
    } catch (e) {
      restore();
      // eslint-disable-next-line no-console
      console.error('[menu-tree] reorder failed', e);
    }
    return true;
  }

  Drupal.menuTreeUi.reorderSibling = reorderSibling;
})(Drupal);
