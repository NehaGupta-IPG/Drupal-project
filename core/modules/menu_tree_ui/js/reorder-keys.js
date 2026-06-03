/**
 * @file
 * Shift+Arrow sibling reorder.
 *
 * Same server endpoint as drag (POST /move). Same optimistic-then-revert
 * shape. Kept in its own module because it spans DOM mutation + fetch +
 * announce, which doesn't really belong in keyboard.js.
 *
 * Exposes Drupal.menuTreeUi.reorderSibling.
 */

((Drupal) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};

  const ROW = 'li[role="treeitem"]';

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
      direction === 'up' ? row.previousElementSibling : row.nextElementSibling;
    if (
      !(sibling instanceof HTMLElement) ||
      sibling.getAttribute('role') !== 'treeitem'
    ) {
      return false;
    }
    const container = row.parentElement;
    if (!(container instanceof HTMLUListElement)) {
      return false;
    }

    const parentId = container.closest(ROW)?.dataset.id ?? '';
    const oldNext = row.nextElementSibling;

    // Optimistic swap.
    if (direction === 'up') {
      container.insertBefore(row, sibling);
    } else {
      container.insertBefore(row, sibling.nextElementSibling);
    }

    const newNext = row.nextElementSibling;
    const beforeId =
      newNext instanceof HTMLElement &&
      newNext.getAttribute('role') === 'treeitem'
        ? (newNext.dataset.id ?? null)
        : null;

    row.focus();
    row.classList.add('menu-tree-ui-row--flash');
    window.setTimeout(() => {
      row.classList.remove('menu-tree-ui-row--flash');
    }, 600);

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
        container.insertBefore(row, oldNext);
        if (Drupal.announce) {
          Drupal.announce('Move failed — restored.', 'assertive');
        }
      }
    } catch (e) {
      container.insertBefore(row, oldNext);
      // eslint-disable-next-line no-console
      console.error('[menu-tree] reorder failed', e);
    }
    return true;
  }

  Drupal.menuTreeUi.reorderSibling = reorderSibling;
})(Drupal);
