/**
 * @file
 * Lazy-load + expand/collapse for the menu tree.
 *
 * Collapse flags a row's descendant run with data-hidden (CSS hides them) and
 * leaves the rows in the DOM; re-expand clears the flag. There are no nested
 * <ul role="group"> elements — children are sibling <tr>s right after their
 * parent. "Is this subtree already loaded" is tracked by row.dataset.loaded
 * rather than the presence of a child <ul>.
 *
 * Hands off row construction to row-builder and visibility tracking to
 * RowWindow. Exposes Drupal.menuTreeUi.ExpandManager.
 */

((Drupal) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};
  const ns = Drupal.menuTreeUi;
  const { buildRows, buildRow } = ns;
  const {
    descendantRows,
    lastDescendantRow,
    childRows,
    levelOf,
    findRow,
    setRowLevel,
    setRowControlsActive,
  } = Drupal.treegrid.treeNav;

  const ROW = 'tr[role="row"]';

  /**
   * Rewrites a row's aria-level and indent, cascading into its child rows so
   * descendant levels stay consistent.
   *
   * No-op when the depth hasn't changed, so reused rows in a same-level
   * reorder pay only a single attribute read.
   *
   * @param {HTMLTableRowElement} row
   *   The row to rebase.
   * @param {number} newDepth
   *   The depth the row should now render at.
   */
  function rebaseRowLevel(row, newDepth) {
    const oldDepth = Number(row.getAttribute('aria-level') ?? '1');
    if (oldDepth === newDepth) {
      return;
    }

    setRowLevel(row, newDepth);
    childRows(row).forEach((child) => {
      rebaseRowLevel(child, newDepth + 1);
    });
  }

  class ExpandManager {
    /**
     * @param {string} childrenUrl
     *   The endpoint returning one level of children as JSON.
     * @param {?Drupal.menuTreeUi.RowWindow} rowWindow
     *   The windowing helper, or undefined when virtualization is off.
     * @param {HTMLTableElement} tree
     *   The root treegrid element.
     */
    constructor(childrenUrl, rowWindow, tree) {
      this.childrenUrl = childrenUrl;
      this.rowWindow = rowWindow;
      this.tree = tree;
      this.inFlight = new Map();
    }

    /**
     * Toggles a row between expanded and collapsed.
     *
     * @param {HTMLElement} row
     *   The parent row.
     *
     * @return {Promise<void>}
     *   Resolves once the toggle (and any fetch) is done.
     */
    async toggle(row) {
      if (row.getAttribute('aria-expanded') === 'true') {
        this.collapse(row);
      } else {
        await this.expand(row);
      }
    }

    /**
     * Expands a row, lazy-loading its children on first open.
     *
     * @param {HTMLElement} row
     *   The parent row.
     *
     * @return {Promise<void>}
     *   Resolves once children are present.
     */
    async expand(row) {
      const { id } = row.dataset;
      if (!id) {
        return;
      }
      row.setAttribute('aria-expanded', 'true');

      if (row.dataset.loaded === 'true') {
        // showSubtree re-observes each row it reveals; rows that stay collapsed
        // are left unobserved so the observer only ever tracks visible rows.
        this.showSubtree(row);
        return;
      }

      // Avoid double-fetches if the user mashes the toggle.
      const existing = this.inFlight.get(id);
      if (existing) {
        await existing;
        return;
      }
      const promise = this.loadChildren(row, id);
      this.inFlight.set(id, promise);
      try {
        await promise;
      } finally {
        this.inFlight.delete(id);
      }
    }

    /**
     * Collapses a row, hiding its already-loaded descendant rows.
     *
     * @param {HTMLElement} row
     *   The parent row.
     */
    // eslint-disable-next-line class-methods-use-this
    collapse(row) {
      // Never fold away a section that holds a row being carried in move mode:
      // the editor must always be able to see the row they are moving, and the
      // cursor would be lost if it vanished. The grabbed row carries a marker
      // class for the duration of the move.
      if (
        descendantRows(row).some((r) =>
          r.classList.contains('menu-tree-ui-row--grabbed'),
        )
      ) {
        return;
      }
      row.setAttribute('aria-expanded', 'false');
      this.hideSubtree(row);
    }

    /**
     * Flags a row's whole descendant run as hidden.
     *
     * @param {HTMLElement} row
     *   The parent row.
     */
    hideSubtree(row) {
      descendantRows(row).forEach((r) => {
        r.dataset.hidden = 'true';
        // Stop tracking hidden rows: otherwise the observer's entry set grows
        // with every loaded row and never shrinks as sections are collapsed.
        this.rowWindow?.unobserve(r);
      });
    }

    /**
     * Reveals a row's descendants, respecting still-collapsed intermediates.
     *
     * Walks the descendant run; a row deeper than a still-collapsed ancestor
     * stays hidden so re-expanding a parent doesn't pop open grandchildren the
     * user had closed.
     *
     * @param {HTMLElement} row
     *   The parent row.
     */
    showSubtree(row) {
      const base = levelOf(row);
      let n = row.nextElementSibling;
      let hideBelow = Infinity;
      while (n && n.matches(ROW)) {
        const lvl = levelOf(n);
        if (lvl <= base) {
          break;
        }
        if (lvl <= hideBelow) {
          delete n.dataset.hidden;
          this.rowWindow?.observe(n);
          hideBelow =
            n.dataset.hasChildren === 'true' &&
            n.getAttribute('aria-expanded') !== 'true'
              ? lvl
              : Infinity;
        }
        n = n.nextElementSibling;
      }
    }

    /**
     * Reconciles a parent's children in the DOM against the server's truth.
     *
     * Surgical diff rather than blow-the-children-away-and-rebuild: existing
     * rows are reused by data-id so their subtrees and state survive (each
     * carries its descendant run as a block); rows new in the server response
     * are freshly built; rows gone from the response are removed with their
     * blocks; order follows the server; reused rows whose level changed are
     * rebased. No-ops when the parent row isn't in the DOM.
     *
     * @param {string} parentId
     *   Plugin ID of the parent, or '' for the root level.
     *
     * @return {Promise<void>}
     *   Resolves once the children are reconciled.
     */
    async refresh(parentId) {
      const isRoot = parentId === '';
      const tbody = this.tree.querySelector('tbody');
      const parentRow = isRoot ? tbody : findRow(this.tree, parentId);
      if (!parentRow) {
        return;
      }

      const items = await this.fetchChildren(parentId);
      if (items === null) {
        return;
      }

      // Snapshot existing direct children with their descendant blocks BEFORE
      // any DOM change, so reused rows can carry their subtrees across.
      const existingRows = isRoot
        ? Array.from(tbody.querySelectorAll(`:scope > ${ROW}`)).filter(
            (r) => r.dataset.parentId === '',
          )
        : childRows(parentRow);
      const blocks = new Map();
      existingRows.forEach((child) => {
        const { id } = child.dataset;
        if (id) {
          blocks.set(id, { row: child, block: descendantRows(child) });
        }
      });

      const childDepth = isRoot ? 1 : levelOf(parentRow) + 1;
      const setSize = items.length;
      const result = [];
      const kept = new Set();
      items.forEach((item, index) => {
        const posInSet = index + 1;
        const entry = blocks.get(item.id);
        if (entry) {
          kept.add(item.id);
          rebaseRowLevel(entry.row, childDepth);
          entry.row.dataset.parentId = parentId;
          entry.row.setAttribute('aria-posinset', String(posInSet));
          entry.row.setAttribute('aria-setsize', String(setSize));
          result.push({ row: entry.row, block: entry.block, fresh: false });
        } else {
          result.push({
            row: buildRow(item, childDepth, posInSet, setSize),
            block: [],
            fresh: true,
          });
        }
      });

      // Remove orphan children (and their descendant blocks).
      blocks.forEach((entry, id) => {
        if (!kept.has(id)) {
          entry.block.forEach((r) => r.remove());
          entry.row.remove();
        }
      });

      // Re-insert in server order. insertBefore/after on an existing node is a
      // move (no clone), so this reorders kept rows and inserts fresh ones.
      let anchor = isRoot ? null : parentRow;
      result.forEach(({ row, block }) => {
        if (anchor) {
          anchor.after(row);
        } else {
          tbody.insertBefore(row, tbody.firstChild);
        }
        anchor = row;
        block.forEach((r) => {
          anchor.after(r);
          anchor = r;
        });
      });

      // Attach behaviors + observe only the freshly-built rows.
      result.forEach(({ row, fresh }) => {
        if (fresh) {
          if (Drupal.attachBehaviors) {
            Drupal.attachBehaviors(row);
          }
          // Built rows are not the active row, so keep their controls out of the
          // tab order (set after attachBehaviors, which upgrades the dropbutton).
          setRowControlsActive(row, false);
          this.rowWindow?.observe(row);
        }
      });
    }

    /**
     * Fetches one level of children.
     *
     * @param {string} parentId
     *   Plugin ID of the parent, or '' for the root level.
     *
     * @return {Promise<?Array<object>>}
     *   The child items, or null on transport failure.
     */
    async fetchChildren(parentId) {
      try {
        const url = new URL(this.childrenUrl, window.location.origin);
        if (parentId !== '') {
          url.searchParams.set('parent', parentId);
        }
        const response = await fetch(url.toString(), {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
        });
        if (!response.ok) {
          // eslint-disable-next-line no-console
          console.error('[menu-tree] children fetch failed', response.status);
          return null;
        }
        const data = await response.json();
        return data.items;
      } catch (e) {
        // eslint-disable-next-line no-console
        console.error('[menu-tree] children fetch error', e);
        return null;
      }
    }

    /**
     * Loads and inserts a row's children on first expand.
     *
     * @param {HTMLElement} row
     *   The parent row.
     * @param {string} parentId
     *   The parent's plugin ID.
     *
     * @return {Promise<void>}
     *   Resolves once children are inserted (or the fetch failed).
     */
    async loadChildren(row, parentId) {
      row.dataset.loading = 'true';
      try {
        const items = await this.fetchChildren(parentId);
        if (items === null) {
          row.setAttribute('aria-expanded', 'false');
          return;
        }
        const depth = levelOf(row) + 1;
        const fragment = buildRows(items, depth);
        const inserted = Array.from(fragment.children);
        const anchor = lastDescendantRow(row) ?? row;
        anchor.after(fragment);
        row.dataset.loaded = 'true';
        inserted.forEach((r) => {
          if (Drupal.attachBehaviors) {
            Drupal.attachBehaviors(r);
          }
          // Built rows are not the active row, so keep their controls out of the
          // tab order (set after attachBehaviors, which upgrades the dropbutton).
          setRowControlsActive(r, false);
          this.rowWindow?.observe(r);
        });
      } finally {
        delete row.dataset.loading;
      }
    }
  }

  Drupal.menuTreeUi.ExpandManager = ExpandManager;
})(Drupal);
