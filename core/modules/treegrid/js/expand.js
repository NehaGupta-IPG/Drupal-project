/**
 * @file
 * Lazy-load + expand/collapse for the treegrid.
 *
 * Collapse flags a row's descendant run with data-treegrid-hidden (CSS hides
 * them) and leaves the rows in the DOM; re-expand clears the flag. There are no
 * nested <ul role="group"> elements — children are sibling <tr>s right after
 * their parent. "Is this subtree already loaded" is tracked by
 * row.dataset.treegridLoaded rather than the presence of a child <ul>.
 *
 * Rows come from the server through core's htmx pipeline, into a hidden staging
 * table the element owns, and are placed from there. Visibility tracking is
 * RowWindow's. Exposes Drupal.treegrid.ExpandManager.
 */

((Drupal, htmx) => {
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
   * aria-label, set by the consumer's row template) so the editor hears which
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
     */
    constructor(element) {
      this.element = element;
      // Level loads waiting for or running in a queue unit, keyed by row id.
      this.queued = new Map();
      // Level loads running inline in a unit, keyed by row id: the row and the
      // load's promise.
      this.loading = new Map();
    }

    /**
     * @return {string}
     *   The endpoint returning one level of children as rows.
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
     * The tbody of the hidden table loaded rows are swapped into.
     *
     * @return {?HTMLTableSectionElement}
     *   The staging tbody.
     */
    get staging() {
      return this.element.querySelector('[data-treegrid-staging] > tbody');
    }

    /**
     * Loads one level's rows into staging through htmx.
     *
     * The response is gated: a status of 400 or more, or a response without
     * the X-Treegrid-Rows marker, is cancelled before htmx swaps or loads
     * anything from it.
     *
     * @param {HTMLElement} source
     *   The element the request belongs to: the parent row, or the element
     *   itself for the top level.
     * @param {string} parentId
     *   The parent's plugin ID, or '' for the root level.
     *
     * @return {Promise<{failed: boolean, status: number}>}
     *   Whether the load failed, and the HTTP status (0 when no response
     *   arrived).
     */
    async requestRows(source, parentId) {
      const url = new URL(this.childrenUrl, window.location.origin);
      if (parentId !== '') {
        url.searchParams.set('parent', parentId);
      }
      let ctx = null;
      let rejected = false;
      let errored = false;
      const onBeforeResponse = (event) => {
        if (event.detail.ctx?.sourceElement !== source) {
          return;
        }
        ctx = event.detail.ctx;
        const { response } = ctx;
        if (
          response.status >= 400 ||
          !response.headers.has('X-Treegrid-Rows')
        ) {
          rejected = true;
          event.preventDefault();
        }
      };
      const onError = (event) => {
        if (event.detail.ctx?.sourceElement === source) {
          errored = true;
        }
      };
      document.addEventListener('htmx:before:response', onBeforeResponse);
      document.addEventListener('htmx:error', onError);
      try {
        await htmx.ajax('GET', url.toString(), {
          source,
          target: this.staging,
          swap: 'beforeend ignoreTitle:true',
          select: '[data-treegrid-rows] > tr',
        });
      } finally {
        document.removeEventListener('htmx:before:response', onBeforeResponse);
        document.removeEventListener('htmx:error', onError);
      }
      return {
        failed: !ctx || rejected || errored,
        status: ctx?.response.status ?? 0,
      };
    }

    /**
     * The rows currently in staging.
     *
     * @return {Array<HTMLTableRowElement>}
     *   The staged rows, in order.
     */
    stagedRows() {
      return Array.from(this.staging?.children ?? []);
    }

    /**
     * Detaches and removes whatever is left in staging.
     */
    emptyStaging() {
      this.stagedRows().forEach((row) => {
        Drupal.detachBehaviors?.(row);
        row.remove();
      });
    }

    /**
     * Sets a placed row's position, level and tab stop, and starts tracking it.
     *
     * The server's values for these are not relied on.
     *
     * @param {HTMLTableRowElement} row
     *   The row, already in the tree.
     * @param {number} depth
     *   The depth the row renders at.
     * @param {number} index
     *   The 0-based index of the row in its level.
     * @param {number} size
     *   The number of rows in the level.
     * @param {boolean} hidden
     *   Whether the row's parent is collapsed or hidden.
     */
    initPlacedRow(row, depth, index, size, hidden) {
      row.setAttribute('aria-posinset', String(index + 1));
      row.setAttribute('aria-setsize', String(size));
      setRowLevel(row, depth);
      row.setAttribute('tabindex', '-1');
      setRowControlsActive(row, false);
      if (hidden) {
        row.dataset.treegridHidden = 'true';
      } else {
        this.rowWindow?.observe(row);
      }
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
      if (existing?.row === row) {
        return existing.promise;
      }
      if (existing) {
        // The entry belongs to a row that has since been replaced.
        existing.canceled = true;
        existing.resolve(false);
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
        if (!row.isConnected) {
          this.clearLoading(row);
          entry.resolve(false);
          return;
        }
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
      if (running?.row === row) {
        return running.promise;
      }
      const promise = this.loadLevel(row, id, announce);
      const load = { row, promise };
      this.loading.set(id, load);
      try {
        const opened = await promise;
        this.settleWaiting(row, opened);
        return opened;
      } finally {
        if (this.loading.get(id) === load) {
          this.loading.delete(id);
        }
      }
    }

    /**
     * Settles a waiting queued load of a row that was just loaded inline.
     *
     * The queued unit is marked canceled, so it does not reopen the row after
     * the load's result has been handed over.
     *
     * @param {HTMLElement} row
     *   The parent row.
     * @param {boolean} opened
     *   The result of the inline load.
     */
    settleWaiting(row, opened) {
      const { id } = row.dataset;
      const entry = this.queued.get(id);
      if (entry?.row !== row || entry.state !== 'waiting') {
        return;
      }
      entry.canceled = true;
      this.queued.delete(id);
      entry.resolve(opened);
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
     * are taken from staging; rows gone from the response are removed with their
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

      try {
        const { failed } = await this.requestRows(
          isRoot ? this.element : parentRow,
          parentId,
        );
        if (failed) {
          return false;
        }
        if (!parentRow.isConnected) {
          return true;
        }
        const staged = this.stagedRows();
        if (!isRoot) {
          parentRow.dataset.hasChildren = staged.length > 0 ? 'true' : 'false';
          if (parentRow.dataset.treegridLoaded !== 'true') {
            // Rows inserted under a parent that was never opened would leave a
            // level that looks loaded but is not.
            return true;
          }
        }
        this.reconcile(parentId, parentRow, staged);
        return true;
      } finally {
        this.emptyStaging();
      }
    }

    /**
     * Reconciles a parent's children in the DOM against the staged rows.
     *
     * @param {string} parentId
     *   Plugin ID of the parent, or '' for the root level.
     * @param {HTMLElement} parentRow
     *   The parent row, or the tbody for the root level.
     * @param {Array<HTMLTableRowElement>} staged
     *   The level's rows as the server rendered them, in order.
     */
    reconcile(parentId, parentRow, staged) {
      const isRoot = parentId === '';
      const tbody = this.tree.querySelector('tbody');
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
      const setSize = staged.length;
      const result = [];
      const kept = new Set();
      staged.forEach((stagedRow, index) => {
        const posInSet = index + 1;
        const entry = blocks.get(stagedRow.dataset.id);
        if (entry) {
          kept.add(stagedRow.dataset.id);
          rebaseRowLevel(entry.row, childDepth);
          entry.row.dataset.parentId = parentId;
          entry.row.setAttribute('aria-posinset', String(posInSet));
          entry.row.setAttribute('aria-setsize', String(setSize));
          result.push({ row: entry.row, block: entry.block, fresh: false });
        } else {
          result.push({ row: stagedRow, block: [], fresh: true });
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
      // move (no clone), so this reorders kept rows and places new ones.
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

      // Set up only the rows new to the level.
      result.forEach(({ row, fresh }, index) => {
        if (fresh) {
          this.initPlacedRow(row, childDepth, index, setSize, insertHidden);
        }
      });
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
     *   fetch failed, the level has no rows, or the row was collapsed
     *   meanwhile.
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
        if (!row.isConnected) {
          return false;
        }
        const { failed, status } = await this.requestRows(row, parentId);
        if (failed) {
          if (isParent) {
            row.setAttribute('aria-expanded', 'false');
          }
          this.dispatch('treegrid:level-load-failed', { parentId, status });
          return false;
        }
        if (!row.isConnected) {
          return false;
        }
        this.dispatch(
          'treegrid:before-rows-change',
          { parentId, change: 'insert' },
          false,
        );
        const rows = this.stagedRows();
        const depth = levelOf(row) + 1;
        const hidden =
          row.dataset.treegridHidden === 'true' ||
          row.getAttribute('aria-expanded') === 'false';
        const anchor = lastDescendantRow(row) ?? row;
        anchor.after(...rows);
        row.dataset.treegridLoaded = 'true';
        rows.forEach((r, index) => {
          this.initPlacedRow(r, depth, index, rows.length, hidden);
        });
        if (rows.length === 0) {
          // A level with no rows is a leaf: it counts as loaded but never open.
          row.removeAttribute('aria-expanded');
          row.dataset.hasChildren = 'false';
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
        this.emptyStaging();
        this.clearLoading(row);
      }
    }
  }

  Drupal.treegrid.ExpandManager = ExpandManager;
})(Drupal, htmx);
