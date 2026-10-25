/**
 * @file
 * Lazy-load + expand/collapse for the treegrid.
 *
 * Collapse flags a row's descendant run with data-treegrid-hidden (CSS hides them) and
 * leaves the rows in the DOM; re-expand clears the flag. There are no nested
 * <ul role="group"> elements — children are sibling <tr>s right after their
 * parent. "Is this subtree already loaded" is tracked by row.dataset.treegridLoaded
 * rather than the presence of a child <ul>.
 *
 * Hands off row construction to an injected renderer and visibility tracking to
 * RowWindow. Exposes Drupal.treegrid.ExpandManager.
 */

((Drupal) => {
  Drupal.treegrid = Drupal.treegrid || {};
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

  /**
   * Announces a toggled row by name through the live region.
   *
   * A screen reader announces the expanded/collapsed state itself from the
   * row's aria-expanded change, but labels that change positionally ("row N",
   * counting the header row) instead of by name. This adds the row's name (its
   * aria-label, set by the consumer's row renderer) so the editor hears which
   * item changed. The state word is deliberately left out so the screen reader
   * does not speak it twice. Skipped when a consumer leaves rows unnamed.
   * The aria-label is plain text and the announcer writes HTML, so the name is
   * escaped here.
   *
   * @param {HTMLElement} row
   *   The toggled row.
   */
  function announceToggle(row) {
    const name = row.getAttribute('aria-label');
    if (!name) {
      return;
    }
    Drupal.treegrid.announce(Drupal.checkPlain(name));
  }

  class ExpandManager {
    /**
     * @param {HTMLElement} element
     *   The <drupal-treegrid> element; its queue serializes level loads and
     *   refreshes, and its rowWindow tracks visibility (rebuilt on reconnect,
     *   so it is read late).
     * @param {object} rowBuilder
     *   The consumer's row renderer.
     * @param {function} rowBuilder.buildRow
     *   Builds a single row element from one item and its depth.
     */
    constructor(element, rowBuilder) {
      this.element = element;
      // The row renderer is injected so the engine stays consumer-agnostic; the
      // consumer supplies how its items become DOM through the registered
      // provider. rowBuilder = { buildRow }.
      this.buildRow = rowBuilder.buildRow;
      // Level loads waiting for or running in a queue unit, keyed by row id.
      this.queued = new Map();
      // Level loads running inline in a unit, keyed by row id.
      this.loading = new Map();
    }

    /**
     * @return {string}
     *   The endpoint returning one level of children as JSON.
     */
    get childrenUrl() {
      return this.element.dataset.childrenUrl;
    }

    /**
     * @return {HTMLTableElement}
     *   The root treegrid table.
     */
    get tree() {
      return this.element.tree;
    }

    /**
     * @return {?Drupal.treegrid.RowWindow}
     *   The element's current windowing helper.
     */
    get rowWindow() {
      return this.element.rowWindow;
    }

    /**
     * Builds one level's rows, with their positions in the level.
     *
     * @param {Array<object>} items
     *   The level's items, in order.
     * @param {number} depth
     *   The depth the rows render at.
     *
     * @return {DocumentFragment}
     *   The sibling rows.
     */
    buildLevel(items, depth) {
      const fragment = document.createDocumentFragment();
      items.forEach((item, index) => {
        fragment.appendChild(
          this.buildPositioned(item, depth, index, items.length),
        );
      });
      return fragment;
    }

    /**
     * Builds one row and sets its position in its level.
     *
     * @param {object} item
     *   The item.
     * @param {number} depth
     *   The depth the row renders at.
     * @param {number} index
     *   The 0-based index of the row in its level.
     * @param {number} size
     *   The number of rows in the level.
     *
     * @return {HTMLElement}
     *   The row.
     */
    buildPositioned(item, depth, index, size) {
      const row = this.buildRow(item, depth);
      row.setAttribute('aria-posinset', String(index + 1));
      row.setAttribute('aria-setsize', String(size));
      return row;
    }

    /**
     * Toggles a row between expanded and collapsed (a user's toggle).
     *
     * Toggling a row whose queued load is still waiting cancels the wait.
     *
     * @param {HTMLElement} row
     *   The parent row.
     *
     * @return {Promise<void>}
     *   Resolves once the toggle (and any fetch) is done.
     */
    async toggle(row) {
      if (this.cancelWait(row)) {
        return;
      }
      if (row.getAttribute('aria-expanded') === 'true') {
        this.collapseRow(row, true);
      } else {
        await this.expandRow(row, true);
      }
    }

    /**
     * Opens a row, loading its children through the element's queue first.
     *
     * A row whose children are already loaded opens at once. Otherwise the
     * load waits its turn in the queue; until then the row carries
     * data-treegrid-loading="waiting" and aria-busy, and a second call returns
     * the same promise.
     *
     * @param {HTMLElement} row
     *   The parent row.
     * @param {boolean} [announce]
     *   Whether to announce the row by name (user-initiated toggles do).
     *
     * @return {Promise<boolean>}
     *   True once the row is open and its level is in. False when the load
     *   failed or was canceled, or the row has no children.
     */
    expandRow(row, announce = false) {
      const { id } = row.dataset;
      if (!id) {
        return Promise.resolve(false);
      }
      if (row.dataset.treegridLoaded === 'true') {
        return Promise.resolve(this.openLoaded(row, announce));
      }
      const existing = this.queued.get(id);
      if (existing) {
        return existing.promise;
      }

      const entry = { row, announce, state: 'waiting', canceled: false };
      entry.promise = new Promise((resolve) => {
        entry.resolve = resolve;
      });
      this.queued.set(id, entry);
      row.dataset.treegridLoading = 'waiting';
      row.setAttribute('aria-busy', 'true');
      this.element
        .exclusive(() => this.runQueued(entry))
        .catch(() => entry.resolve(false));
      return entry.promise;
    }

    /**
     * Runs a queued level load when its turn comes.
     *
     * @param {object} entry
     *   The queued load: { row, announce, state, canceled, resolve }.
     *
     * @return {Promise<void>}
     *   Resolves once the load has run (or was skipped as canceled).
     */
    async runQueued(entry) {
      const { row } = entry;
      const { id } = row.dataset;
      if (entry.canceled) {
        return;
      }
      entry.state = 'running';
      try {
        // A unit that ran earlier may have loaded the row already; then there
        // is only the showing left to do.
        entry.resolve(await this.expandInline(row, entry.announce));
      } finally {
        if (this.queued.get(id) === entry) {
          this.queued.delete(id);
        }
        entry.resolve(false);
      }
    }

    /**
     * Opens a row inside a running unit, loading its children at once.
     *
     * @param {HTMLElement} row
     *   The parent row.
     * @param {boolean} [announce]
     *   Whether to announce the row by name.
     *
     * @return {Promise<boolean>}
     *   True once the row is open and its level is in.
     */
    async expandInline(row, announce = false) {
      const { id } = row.dataset;
      if (!id) {
        return false;
      }
      if (row.dataset.treegridLoaded === 'true') {
        return this.openLoaded(row, announce);
      }
      const running = this.loading.get(id);
      if (running) {
        return running;
      }
      const promise = this.loadLevel(row, id, announce);
      this.loading.set(id, promise);
      try {
        return await promise;
      } finally {
        this.loading.delete(id);
      }
    }

    /**
     * Opens a row whose children are already in the DOM.
     *
     * @param {HTMLElement} row
     *   The parent row.
     * @param {boolean} announce
     *   Whether to announce the row by name.
     *
     * @return {boolean}
     *   True if the row opened; false when its level has no rows.
     */
    openLoaded(row, announce) {
      this.clearLoading(row);
      if (descendantRows(row).length === 0) {
        return false;
      }
      row.setAttribute('aria-expanded', 'true');
      if (announce) {
        announceToggle(row);
      }
      // showSubtree re-observes each row it reveals; rows that stay collapsed
      // are left unobserved so the observer only ever tracks visible rows.
      this.showSubtree(row);
      return true;
    }

    /**
     * Cancels a queued level load that has not started.
     *
     * @param {HTMLElement} row
     *   The parent row.
     *
     * @return {boolean}
     *   True if a waiting load was canceled.
     */
    cancelWait(row) {
      const { id } = row.dataset;
      const entry = id ? this.queued.get(id) : null;
      if (!entry || entry.state !== 'waiting') {
        return false;
      }
      entry.canceled = true;
      this.queued.delete(id);
      this.clearLoading(row);
      entry.resolve(false);
      return true;
    }

    /**
     * Removes the loading marks from a row.
     *
     * @param {HTMLElement} row
     *   The parent row.
     */
    // eslint-disable-next-line class-methods-use-this
    clearLoading(row) {
      delete row.dataset.treegridLoading;
      row.removeAttribute('aria-busy');
    }

    /**
     * Collapses a row, hiding its already-loaded descendant rows.
     *
     * Collapsing a row whose queued load is still waiting cancels the wait.
     * A row holding a descendant a feature asked to keep visible (e.g. a row
     * being carried in move mode) stays open: the editor must be able to see
     * it, and the cursor would be lost if it vanished. The feature marks such
     * a row with data-treegrid-keep-visible for the duration.
     *
     * @param {HTMLElement} row
     *   The parent row.
     * @param {boolean} [announce]
     *   Whether to announce the new state by name (user-initiated toggles do).
     *
     * @return {boolean}
     *   False when a keep-visible descendant blocks the collapse.
     */
    collapseRow(row, announce = false) {
      if (this.cancelWait(row)) {
        return true;
      }
      if (
        descendantRows(row).some(
          (r) => r.dataset.treegridKeepVisible === 'true',
        )
      ) {
        return false;
      }
      row.setAttribute('aria-expanded', 'false');
      if (announce) {
        announceToggle(row);
      }
      this.hideSubtree(row);
      return true;
    }

    /**
     * Flags a row's whole descendant run as hidden.
     *
     * @param {HTMLElement} row
     *   The parent row.
     */
    hideSubtree(row) {
      descendantRows(row).forEach((r) => {
        r.dataset.treegridHidden = 'true';
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
          delete n.dataset.treegridHidden;
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
     * @return {Promise<boolean>}
     *   True once the children are reconciled (or there was nothing to do);
     *   false when the server fetch failed, so the caller can recover.
     */
    async refresh(parentId) {
      // First thing: a listener (move mode giving up a carry) changes rows the
      // snapshot below would otherwise already have captured.
      this.dispatch(
        'treegrid:before-rows-change',
        { parentId, change: 'reconcile' },
        false,
      );
      const isRoot = parentId === '';
      const tbody = this.tree.querySelector('tbody');
      const parentRow = isRoot ? tbody : findRow(this.tree, parentId);
      if (!parentRow) {
        return true;
      }

      const { items } = await this.fetchChildren(parentId);
      if (items === null) {
        return false;
      }
      if (!parentRow.isConnected) {
        return true;
      }
      if (!isRoot) {
        parentRow.dataset.hasChildren = items.length > 0 ? 'true' : 'false';
        if (parentRow.dataset.treegridLoaded !== 'true') {
          // Rows inserted under a parent that was never opened would leave a
          // level that looks loaded but is not.
          return true;
        }
      }
      // Rows new to a collapsed (or hidden) parent come in hidden.
      const insertHidden =
        !isRoot &&
        (parentRow.getAttribute('aria-expanded') !== 'true' ||
          parentRow.dataset.treegridHidden === 'true');

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
            row: this.buildPositioned(item, childDepth, index, setSize),
            block: [],
            fresh: true,
          });
        }
      });

      // Remove orphan children (and their descendant blocks).
      blocks.forEach((entry, id) => {
        if (!kept.has(id)) {
          entry.block.forEach((r) => this.removeRow(r));
          this.removeRow(entry.row);
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
          if (insertHidden) {
            row.dataset.treegridHidden = 'true';
          } else {
            this.rowWindow?.observe(row);
          }
        }
      });
      return true;
    }

    /**
     * Removes a row from the DOM, releasing what is attached to it.
     *
     * Detaches the row's behaviors and stops its windowing, so the row window
     * does not retain a detached node.
     *
     * @param {HTMLElement} row
     *   The row to remove.
     */
    removeRow(row) {
      if (Drupal.detachBehaviors) {
        Drupal.detachBehaviors(row);
      }
      this.rowWindow?.unobserve(row);
      row.remove();
    }

    /**
     * Removes a row's descendant rows and forgets that they were loaded.
     *
     * DOM only, never queued.
     *
     * @param {HTMLElement} row
     *   The parent row.
     */
    clearChildren(row) {
      descendantRows(row).forEach((r) => this.removeRow(r));
      delete row.dataset.treegridLoaded;
    }

    /**
     * Dispatches an event on the element.
     *
     * @param {string} type
     *   The event type.
     * @param {object} detail
     *   The event detail.
     * @param {boolean} [bubbles]
     *   Whether the event bubbles.
     */
    dispatch(type, detail, bubbles = true) {
      this.element.dispatchEvent(new CustomEvent(type, { bubbles, detail }));
    }

    /**
     * Fetches one level of children.
     *
     * @param {string} parentId
     *   Plugin ID of the parent, or '' for the root level.
     *
     * @return {Promise<{items: ?Array<object>, status: number}>}
     *   The child items (null on failure) and the HTTP status (0 for a network
     *   error).
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
          console.error('[treegrid] children fetch failed', response.status);
          return { items: null, status: response.status };
        }
        const data = await response.json();
        return { items: data.items, status: response.status };
      } catch (e) {
        // eslint-disable-next-line no-console
        console.error('[treegrid] children fetch error', e);
        return { items: null, status: 0 };
      }
    }

    /**
     * Loads and inserts a row's children, marking the row loaded.
     *
     * @param {HTMLElement} row
     *   The parent row.
     * @param {string} parentId
     *   The parent's plugin ID.
     * @param {boolean} announce
     *   Whether to announce the row by name.
     *
     * @return {Promise<boolean>}
     *   True if the row ended up open with its children in. False when the
     *   fetch failed, the level has no rows, or the row was collapsed meanwhile.
     */
    async loadLevel(row, parentId, announce) {
      const isParent = row.dataset.hasChildren === 'true';
      row.dataset.treegridLoading = 'loading';
      row.setAttribute('aria-busy', 'true');
      if (isParent) {
        row.setAttribute('aria-expanded', 'true');
        if (announce) {
          announceToggle(row);
        }
      }
      try {
        const { items, status } = await this.fetchChildren(parentId);
        if (items === null) {
          if (isParent) {
            row.setAttribute('aria-expanded', 'false');
          }
          this.dispatch('treegrid:level-load-failed', { parentId, status });
          return false;
        }
        this.dispatch(
          'treegrid:before-rows-change',
          { parentId, change: 'insert' },
          false,
        );
        const depth = levelOf(row) + 1;
        const fragment = this.buildLevel(items, depth);
        const inserted = Array.from(fragment.children);
        const anchor = lastDescendantRow(row) ?? row;
        anchor.after(fragment);
        row.dataset.treegridLoaded = 'true';
        inserted.forEach((r) => {
          if (Drupal.attachBehaviors) {
            Drupal.attachBehaviors(r);
          }
          // Built rows are not the active row, so keep their controls out of the
          // tab order (set after attachBehaviors, which upgrades the dropbutton).
          setRowControlsActive(r, false);
          this.rowWindow?.observe(r);
        });
        if (items.length === 0) {
          // A level with no rows is a leaf: it counts as loaded but never open.
          row.removeAttribute('aria-expanded');
          this.dispatch('treegrid:level-loaded', { parentId });
          return false;
        }
        row.dataset.hasChildren = 'true';
        row.setAttribute(
          'aria-expanded',
          row.getAttribute('aria-expanded') ?? 'true',
        );
        // The row may have been collapsed while this fetch was in flight, when
        // there were no child rows yet to hide. Hide the children that just
        // arrived so they do not show under a now-collapsed row.
        const open = row.getAttribute('aria-expanded') === 'true';
        if (!open) {
          this.hideSubtree(row);
        }
        this.dispatch('treegrid:level-loaded', { parentId });
        return open;
      } finally {
        this.clearLoading(row);
      }
    }
  }

  Drupal.treegrid.ExpandManager = ExpandManager;
})(Drupal);
