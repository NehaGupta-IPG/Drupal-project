/**
 * @file
 * Tree-walking and focus helpers for <menu-tree>.
 *
 * "Visible" here means "in DOM and not under a collapsed ancestor" — not
 * "in the viewport". Used by keyboard nav, drag intent, and jump-to.
 *
 * Exposes helpers on Drupal.menuTreeUi.treeNav. No build step: this file is
 * a plain script loaded (in dependency order) by menu_tree_ui.libraries.yml.
 */

((Drupal) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};

  const ROW = 'li[role="treeitem"]';

  /**
   * Collects all visible rows in document order.
   *
   * Walks the tree depth-first, skipping subtrees of any row whose
   * aria-expanded is 'false'. O(n) over rows currently in DOM; because
   * subtrees only enter the DOM after an expand, the working set stays small.
   *
   * @param {HTMLUListElement} tree
   *   The root tree element.
   *
   * @return {HTMLElement[]}
   *   The visible rows, in document order.
   */
  function visibleRows(tree) {
    const out = [];
    function walk(list) {
      if (!list) {
        return;
      }
      list.querySelectorAll(`:scope > ${ROW}`).forEach((li) => {
        out.push(li);
        if (li.getAttribute('aria-expanded') === 'true') {
          walk(li.querySelector(':scope > ul[role="group"]'));
        }
      });
    }
    walk(tree);
    return out;
  }

  /**
   * Finds the first visible row in document order.
   *
   * @param {HTMLUListElement} tree
   *   The root tree element.
   *
   * @return {?HTMLElement}
   *   The first row, or null.
   */
  function firstVisibleRow(tree) {
    return tree.querySelector(ROW);
  }

  /**
   * Finds the last visible row, walking through expanded subtrees.
   *
   * @param {HTMLUListElement} tree
   *   The root tree element.
   *
   * @return {?HTMLElement}
   *   The last row, or null.
   */
  function lastVisibleRow(tree) {
    const rows = visibleRows(tree);
    return rows[rows.length - 1] ?? null;
  }

  /**
   * Finds the next visible row after a given row.
   *
   * @param {HTMLUListElement} tree
   *   The root tree element.
   * @param {HTMLElement} row
   *   The reference row.
   *
   * @return {?HTMLElement}
   *   The next row, or null at the end.
   */
  function nextVisibleRow(tree, row) {
    const rows = visibleRows(tree);
    const i = rows.indexOf(row);
    return i >= 0 ? (rows[i + 1] ?? null) : null;
  }

  /**
   * Finds the previous visible row before a given row.
   *
   * @param {HTMLUListElement} tree
   *   The root tree element.
   * @param {HTMLElement} row
   *   The reference row.
   *
   * @return {?HTMLElement}
   *   The previous row, or null at the start.
   */
  function previousVisibleRow(tree, row) {
    const rows = visibleRows(tree);
    const i = rows.indexOf(row);
    return i > 0 ? (rows[i - 1] ?? null) : null;
  }

  /**
   * Finds a row by its data-id anywhere in the tree.
   *
   * @param {HTMLUListElement} tree
   *   The root tree element.
   * @param {string} id
   *   The link plugin ID stored in data-id.
   *
   * @return {?HTMLElement}
   *   The matching row, or null.
   */
  function findRow(tree, id) {
    return tree.querySelector(`${ROW}[data-id="${CSS.escape(id)}"]`);
  }

  /**
   * Applies the roving tabindex and moves focus to a row.
   *
   * Clears tabindex on all rows, sets it to 0 on the target, and focuses it.
   * No-op on null so callers can pass null safely.
   *
   * @param {HTMLUListElement} tree
   *   The root tree element.
   * @param {?HTMLElement} row
   *   The row to focus, or null.
   */
  function focusRow(tree, row) {
    if (!row) {
      return;
    }
    tree.querySelectorAll(ROW).forEach((r) => {
      r.tabIndex = -1;
    });
    row.tabIndex = 0;
    row.focus();
  }

  Drupal.menuTreeUi.treeNav = {
    visibleRows,
    firstVisibleRow,
    lastVisibleRow,
    nextVisibleRow,
    previousVisibleRow,
    findRow,
    focusRow,
  };
})(Drupal);
