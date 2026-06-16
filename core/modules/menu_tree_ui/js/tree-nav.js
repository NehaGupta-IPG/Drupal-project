/**
 * @file
 * Tree-walking and focus helpers for <menu-tree>.
 *
 * The tree is a flat <table role="treegrid">: every row is a sibling <tr> in
 * one <tbody>, and hierarchy is read from aria-level (depth) plus data-parent-id
 * (linkage). The load-bearing primitive is descendantRows(): a row's descendants
 * are the contiguous run of <tr>s immediately after it, up to the first row at a
 * shallower-or-equal level. childRows()/lastDescendantRow() build on it. These
 * replace the structural :scope > ul[role="group"] queries the nested-list tree
 * used.
 *
 * "Visible" means "in DOM and not flagged data-hidden by a collapsed ancestor"
 * — not "in the viewport". Focus lives on a row's drag handle (the per-row tab
 * target), not the <tr>.
 *
 * Exposes helpers on Drupal.menuTreeUi.treeNav. No build step: this file is a
 * plain script loaded (in dependency order) by menu_tree_ui.libraries.yml.
 */

((Drupal) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};

  const ROW = 'tr[role="row"]';
  const HANDLE = '.menu-tree-ui-row__handle';

  /**
   * The level (depth) of a row.
   *
   * @param {HTMLElement} row
   *   The row.
   *
   * @return {number}
   *   The 1-based aria-level.
   */
  function levelOf(row) {
    return Number(row.getAttribute('aria-level'));
  }

  /**
   * All descendant rows of a row, in document order.
   *
   * The contiguous run of <tr>s immediately following the row, up to (not
   * including) the first row whose aria-level is shallower than or equal to the
   * row's. Holds because a parent's whole subtree is always kept contiguous,
   * directly after the parent.
   *
   * @param {HTMLElement} row
   *   The row whose descendants to collect.
   *
   * @return {HTMLElement[]}
   *   The descendant rows, in document order.
   */
  function descendantRows(row) {
    const level = levelOf(row);
    const out = [];
    let next = row.nextElementSibling;
    while (next && next.matches(ROW)) {
      if (levelOf(next) <= level) {
        break;
      }
      out.push(next);
      next = next.nextElementSibling;
    }
    return out;
  }

  /**
   * The last descendant of a row, for computing an insertion anchor.
   *
   * @param {HTMLElement} row
   *   The row.
   *
   * @return {?HTMLElement}
   *   The last descendant, or null when the row has none.
   */
  function lastDescendantRow(row) {
    const rows = descendantRows(row);
    return rows.length ? rows[rows.length - 1] : null;
  }

  /**
   * The direct child rows of a row (one level deeper).
   *
   * @param {HTMLElement} row
   *   The parent row.
   *
   * @return {HTMLElement[]}
   *   The direct children, in document order.
   */
  function childRows(row) {
    const childLevel = levelOf(row) + 1;
    return descendantRows(row).filter((r) => levelOf(r) === childLevel);
  }

  /**
   * The next sibling row at the same level under the same parent.
   *
   * Skips the row's own descendant run, so for an expanded parent it returns
   * the next sibling rather than the parent's first child.
   *
   * @param {HTMLElement} row
   *   The reference row.
   *
   * @return {?HTMLElement}
   *   The next sibling row, or null when the row is last among its siblings.
   */
  function nextSiblingRow(row) {
    const last = lastDescendantRow(row) ?? row;
    const next = last.nextElementSibling;
    if (
      next &&
      next.matches(ROW) &&
      next.dataset.parentId === row.dataset.parentId
    ) {
      return next;
    }
    return null;
  }

  /**
   * Collects all visible rows in document order.
   *
   * Visible = a body row not flagged data-hidden (collapse sets that flag on a
   * row's descendant run). O(n) over rows currently in DOM; because subtrees
   * only enter the DOM after an expand, the working set stays small.
   *
   * @param {HTMLTableElement} tree
   *   The root treegrid element.
   *
   * @return {HTMLElement[]}
   *   The visible rows, in document order.
   */
  function visibleRows(tree) {
    return Array.from(tree.querySelectorAll(ROW)).filter(
      (row) => row.dataset.hidden !== 'true',
    );
  }

  /**
   * Finds the first visible row in document order.
   *
   * @param {HTMLTableElement} tree
   *   The root treegrid element.
   *
   * @return {?HTMLElement}
   *   The first row, or null.
   */
  function firstVisibleRow(tree) {
    return visibleRows(tree)[0] ?? null;
  }

  /**
   * Finds the last visible row in document order.
   *
   * @param {HTMLTableElement} tree
   *   The root treegrid element.
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
   * @param {HTMLTableElement} tree
   *   The root treegrid element.
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
   * @param {HTMLTableElement} tree
   *   The root treegrid element.
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
   * @param {HTMLTableElement} tree
   *   The root treegrid element.
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
   * The focusable drag handle of a row (the per-row keyboard target).
   *
   * @param {?HTMLElement} row
   *   The row, or null.
   *
   * @return {?HTMLElement}
   *   The handle element, or null.
   */
  function handleOf(row) {
    return row ? row.querySelector(HANDLE) : null;
  }

  /**
   * Resolves the owning row from an element (e.g. an event target).
   *
   * @param {?EventTarget} el
   *   The element, or null.
   *
   * @return {?HTMLElement}
   *   The owning row, or null.
   */
  function rowOf(el) {
    return el instanceof Element ? el.closest(ROW) : null;
  }

  /**
   * Moves focus to a row by focusing its handle, and marks it selected.
   *
   * Clears aria-selected on all rows, sets it on the target, and focuses the
   * target's handle. No-op on null so callers can pass null safely.
   *
   * @param {HTMLTableElement} tree
   *   The root treegrid element.
   * @param {?HTMLElement} row
   *   The row to focus, or null.
   */
  function focusRow(tree, row) {
    if (!row) {
      return;
    }
    tree.querySelectorAll(ROW).forEach((r) => {
      r.setAttribute('aria-selected', 'false');
    });
    row.setAttribute('aria-selected', 'true');
    (handleOf(row) ?? row).focus();
  }

  Drupal.menuTreeUi.treeNav = {
    levelOf,
    descendantRows,
    lastDescendantRow,
    childRows,
    nextSiblingRow,
    visibleRows,
    firstVisibleRow,
    lastVisibleRow,
    nextVisibleRow,
    previousVisibleRow,
    findRow,
    handleOf,
    rowOf,
    focusRow,
  };
})(Drupal);
