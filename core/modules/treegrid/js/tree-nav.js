/**
 * @file
 * Tree-walking and focus helpers for <drupal-treegrid>.
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
 * — not "in the viewport". Focus lives on the <tr>, which is the single roving
 * tab stop; the handle is a mouse and drag affordance only.
 *
 * Exposes helpers on Drupal.treegrid.treeNav. No build step: this file is a
 * plain script loaded (in dependency order) by treegrid.libraries.yml.
 */

((Drupal) => {
  Drupal.treegrid = Drupal.treegrid || {};

  const ROW = 'tr[role="row"]';
  // Controls that join the active row's tab sequence. The row is the cursor and
  // the arrow keys drive the tree, so a consumer marks the controls that should
  // be tabbable with data-treegrid-control. Anything not so marked (e.g. a drag
  // handle, or the expand toggle operated by Left/Right/Enter) stays out.
  const ROW_CONTROLS = '[data-treegrid-control]';

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
   * Sets a row's depth: its aria-level and the name cell's indent.
   *
   * The one place the indent step (1.25rem per level) lives; row construction
   * (row-builder), lazy-load rebasing (expand), and the keyboard carry
   * (move-mode) all route through here.
   *
   * @param {HTMLElement} row
   *   The row.
   * @param {number} level
   *   The 1-based level to apply.
   */
  function setRowLevel(row, level) {
    row.setAttribute('aria-level', String(level));
    const name = row.querySelector('[data-treegrid-name-cell]');
    if (name) {
      name.style.paddingInlineStart = `${(level - 1) * 1.25}rem`;
    }
  }

  /**
   * Announces a message through Drupal's live-region announcer, if present.
   *
   * @param {string} message
   *   The message.
   * @param {('polite'|'assertive')} [priority]
   *   The live-region priority.
   */
  function announce(message, priority) {
    if (Drupal.announce) {
      Drupal.announce(message, priority);
    }
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

  // Focusable elements a marked control (or the descendants of a marked
  // control container) can be.
  const FOCUSABLE = 'a, button, input, select, textarea';

  /**
   * The within-row controls that join the active row's tab sequence.
   *
   * A consumer marks controls with data-treegrid-control. A marked element that
   * is itself focusable is a control; a marked container (e.g. a rendered
   * operations dropbutton) contributes its focusable descendants, so the
   * container itself never becomes a tab stop.
   *
   * @param {?HTMLElement} row
   *   The row, or null.
   *
   * @return {HTMLElement[]}
   *   The controls, or an empty array for null.
   */
  function rowControls(row) {
    if (!row) {
      return [];
    }
    const controls = [];
    row.querySelectorAll(ROW_CONTROLS).forEach((el) => {
      if (el.matches(FOCUSABLE)) {
        controls.push(el);
      } else {
        el.querySelectorAll(FOCUSABLE).forEach((child) => controls.push(child));
      }
    });
    return controls;
  }

  /**
   * Adds or removes a row's controls from the page tab order.
   *
   * Only the active row's controls are tabbable, so the whole tree is one Tab
   * stop: Tab from the focused row steps through its controls, then leaves.
   *
   * @param {?HTMLElement} row
   *   The row, or null.
   * @param {boolean} active
   *   TRUE to make the controls tabbable, FALSE to take them out.
   */
  function setRowControlsActive(row, active) {
    rowControls(row).forEach((control) => {
      control.setAttribute('tabindex', active ? '0' : '-1');
    });
  }

  /**
   * Moves focus to a row and makes it the single roving tab stop.
   *
   * Focus goes on the <tr> itself, which carries the row's level, position, and
   * expanded state, so a screen reader announces the hierarchy. The previously
   * focused row and its controls drop to tabindex="-1" while the new row and its
   * controls become tabbable, so the tree stays one stop in the page's tab
   * order. No-op on null so callers can pass null safely.
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
    const current = tree.querySelector(`${ROW}[tabindex="0"]`);
    if (current && current !== row) {
      current.setAttribute('tabindex', '-1');
      setRowControlsActive(current, false);
    }
    row.setAttribute('tabindex', '0');
    setRowControlsActive(row, true);
    row.focus();
  }

  /**
   * Primes the roving tab order across the whole tree.
   *
   * Takes every row's controls out of the tab order, then restores the active
   * row's (the one already carrying tabindex="0"). Run once the tree is in the
   * DOM; per-row transitions are handled by focusRow and newly built rows start
   * inactive.
   *
   * @param {HTMLTableElement} tree
   *   The root treegrid element.
   */
  function initRovingTabindex(tree) {
    tree
      .querySelectorAll(ROW)
      .forEach((row) => setRowControlsActive(row, false));
    setRowControlsActive(tree.querySelector(`${ROW}[tabindex="0"]`), true);
  }

  Drupal.treegrid.treeNav = {
    levelOf,
    setRowLevel,
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
    rowOf,
    focusRow,
    rowControls,
    setRowControlsActive,
    initRovingTabindex,
  };
  // A module-wide announce helper (not tree-specific) so every file can speak
  // through the one live region without re-checking Drupal.announce.
  Drupal.treegrid.announce = announce;
})(Drupal);
