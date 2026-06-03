/**
 * @file
 * Lazy-load + expand/collapse for the menu tree.
 *
 * Re-expanding a previously-loaded subtree just unhides the existing DOM — the
 * children <ul> stays in place, hidden via `hidden`, between collapse and
 * re-expand. That's "is it already loaded" tracking without a parallel Set.
 *
 * Hands off row construction to row-builder and visibility tracking to
 * RowWindow. Exposes Drupal.menuTreeUi.ExpandManager.
 */

((Drupal) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};
  const { buildGroup, buildRow } = Drupal.menuTreeUi;

  const ROW = 'li[role="treeitem"]';

  /**
   * Rewrites a row's aria-level and indent, cascading into any subtree it
   * brought along so descendant levels stay consistent.
   *
   * No-op when the depth hasn't changed, so reused rows in a same-level
   * reorder pay only a single attribute read.
   *
   * @param {HTMLLIElement} row
   *   The row to rebase.
   * @param {number} newDepth
   *   The depth the row should now render at.
   */
  function rebaseRowLevel(row, newDepth) {
    const oldDepth = Number(row.getAttribute('aria-level') ?? '1');
    if (oldDepth === newDepth) {
      return;
    }

    row.setAttribute('aria-level', String(newDepth));
    const content = row.querySelector(':scope > .menu-tree-ui-row__content');
    if (content) {
      content.style.paddingInlineStart = `${(newDepth - 1) * 1.25}rem`;
    }
    const childGroup = row.querySelector(':scope > ul[role="group"]');
    if (!childGroup) {
      return;
    }
    Array.from(childGroup.children).forEach((child) => {
      if (child instanceof HTMLLIElement && child.matches(ROW)) {
        rebaseRowLevel(child, newDepth + 1);
      }
    });
  }

  class ExpandManager {
    /**
     * @param {string} childrenUrl
     *   The endpoint returning one level of children as JSON.
     * @param {?Drupal.menuTreeUi.RowWindow} rowWindow
     *   The windowing helper, or undefined when virtualisation is off.
     * @param {HTMLUListElement} tree
     *   The root tree element.
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

      const group = row.querySelector(':scope > ul[role="group"]');
      if (group) {
        group.hidden = false;
        this.observeAll(group);
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
     * Collapses a row, hiding its already-loaded children.
     *
     * @param {HTMLElement} row
     *   The parent row.
     */
    // eslint-disable-next-line class-methods-use-this
    collapse(row) {
      row.setAttribute('aria-expanded', 'false');
      const group = row.querySelector(':scope > ul[role="group"]');
      if (group) {
        group.hidden = true;
      }
    }

    /**
     * Reconciles a parent's children in the DOM against the server's truth.
     *
     * Surgical diff rather than blow-the-children-away-and-rebuild: existing
     * rows are reused by data-id so their subtrees and state survive; rows new
     * in the server response are freshly built; rows gone from the response
     * are removed; order follows the server; rows whose level changed are
     * rebased. Creates the group element first if the parent row exists but
     * has no group yet (leaf-just-became-a-parent). No-ops when the parent row
     * isn't in the DOM.
     *
     * @param {string} parentId
     *   Plugin ID of the parent, or '' for the root level.
     *
     * @return {Promise<void>}
     *   Resolves once the group is reconciled.
     */
    async refresh(parentId) {
      const groupEl = this.ensureGroupElement(parentId);
      if (!groupEl) {
        return;
      }

      const items = await this.fetchChildren(parentId);
      if (items === null) {
        return;
      }

      const depth = this.depthForGroup(groupEl);
      this.reconcileGroup(groupEl, items, depth);
      if (Drupal.attachBehaviors) {
        Drupal.attachBehaviors(groupEl);
      }
      this.observeAll(groupEl);
    }

    /**
     * Diffs and reorders the direct child rows of a group to match items.
     *
     * Children of those rows are left alone so subtrees survive.
     *
     * @param {HTMLUListElement} groupEl
     *   The group whose children to reconcile.
     * @param {Array<object>} items
     *   The server's child items.
     * @param {number} depth
     *   The depth the children should render at.
     */
    // eslint-disable-next-line class-methods-use-this
    reconcileGroup(groupEl, items, depth) {
      const existing = new Map();
      Array.from(groupEl.children).forEach((child) => {
        if (
          child instanceof HTMLLIElement &&
          child.matches('li[role="treeitem"]')
        ) {
          const { id } = child.dataset;
          if (id) {
            existing.set(id, child);
          }
        }
      });

      const setSize = items.length;
      const ordered = [];
      items.forEach((item, index) => {
        const posInSet = index + 1;
        const reused = existing.get(item.id);
        let li;
        if (reused) {
          existing.delete(item.id);
          rebaseRowLevel(reused, depth);
          reused.setAttribute('aria-posinset', String(posInSet));
          reused.setAttribute('aria-setsize', String(setSize));
          li = reused;
        } else {
          li = buildRow(item, depth, posInSet, setSize);
        }
        ordered.push(li);
      });

      existing.forEach((orphan) => {
        orphan.remove();
      });
      // appendChild on an existing child is a move (no clone), so this works
      // both as a reorder and as an insert for fresh rows.
      ordered.forEach((li) => {
        groupEl.appendChild(li);
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
     * Loads and appends a row's children on first expand.
     *
     * @param {HTMLElement} row
     *   The parent row.
     * @param {string} parentId
     *   The parent's plugin ID.
     *
     * @return {Promise<void>}
     *   Resolves once children are appended (or the fetch failed).
     */
    async loadChildren(row, parentId) {
      row.dataset.loading = 'true';
      try {
        const items = await this.fetchChildren(parentId);
        if (items === null) {
          row.setAttribute('aria-expanded', 'false');
          return;
        }
        const depth = Number(row.getAttribute('aria-level') ?? '1') + 1;
        const group = buildGroup(items, depth);
        row.appendChild(group);
        if (Drupal.attachBehaviors) {
          Drupal.attachBehaviors(group);
        }
        this.observeAll(group);
      } finally {
        delete row.dataset.loading;
      }
    }

    /**
     * Resolves the <ul> holding the direct children of a parent.
     *
     * Creates an empty group for a non-root parent if the row exists but has
     * no group yet (the leaf-just-became-a-parent case). Returns null only
     * when the parent's row itself isn't in the DOM.
     *
     * @param {string} parentId
     *   Plugin ID of the parent, or '' for the root level.
     *
     * @return {?HTMLUListElement}
     *   The group element, or null.
     */
    ensureGroupElement(parentId) {
      if (parentId === '') {
        return this.tree;
      }
      const row = this.tree.querySelector(
        `${ROW}[data-id="${CSS.escape(parentId)}"]`,
      );
      if (!row) {
        return null;
      }
      const existing = row.querySelector(':scope > ul[role="group"]');
      if (existing) {
        return existing;
      }
      const group = document.createElement('ul');
      group.setAttribute('role', 'group');
      group.className = 'menu-tree-ui__list';
      row.appendChild(group);
      return group;
    }

    /**
     * The depth at which a group's children should render.
     *
     * Root → 1; nested → parent row's aria-level + 1.
     *
     * @param {HTMLUListElement} groupEl
     *   The group element.
     *
     * @return {number}
     *   The child depth.
     */
    depthForGroup(groupEl) {
      if (groupEl === this.tree) {
        return 1;
      }
      const parentRow = groupEl.closest(ROW);
      return Number(parentRow?.getAttribute('aria-level') ?? '0') + 1;
    }

    /**
     * Observes every row within a scope for windowing.
     *
     * @param {Element} scope
     *   The element whose descendant rows to observe.
     */
    observeAll(scope) {
      if (!this.rowWindow) {
        return;
      }
      scope.querySelectorAll(ROW).forEach((row) => {
        this.rowWindow.observe(row);
      });
    }
  }

  Drupal.menuTreeUi.ExpandManager = ExpandManager;
})(Drupal);
