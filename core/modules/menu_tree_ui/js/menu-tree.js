/**
 * @file
 * <menu-tree> custom element.
 *
 * Thin orchestrator. Upgrades a server-rendered top-level menu tree (rendered
 * by templates/menu-tree-ui-page.html.twig) into an interactive lazy-loaded
 * tree with full WAI-ARIA tree keyboard navigation, drag-and-drop reorder, and
 * jump-from-search-result.
 *
 * Heavy lifting lives in dedicated files, all attached to Drupal.menuTreeUi:
 *  - expand.js        lazy-load + collapse/expand
 *  - keyboard.js      arrow / Home / End / Enter
 *  - reorder-keys.js  Shift+Arrow sibling reorder
 *  - move-mode.js     keyboard grab/select move (Space to grab)
 *  - jump-to.js       jump from search results (document-level click)
 *  - search.js        dismiss the search results popover
 *  - drag/            pointer-event drag reorder
 *  - tree-nav.js      visible-row enumeration + focus
 *  - row-builder.js   JSON → DOM
 *  - windowing.js     IntersectionObserver row virtualization
 *
 * No Drupal.behaviors: the custom element's connectedCallback fires
 * automatically when a <menu-tree> enters the DOM — including htmx-swapped
 * content — so the behavior/once() re-init plumbing isn't needed.
 *
 * DOM contract:
 *   <menu-tree data-children-url="...">
 *     <table role="treegrid">
 *       <thead>…</thead>
 *       <tbody>
 *         <tr role="row" data-id="..." data-parent-id="..." aria-level="..."> … </tr>
 *         ...
 *       </tbody>
 *     </table>
 *   </menu-tree>
 *
 * Rows are flat siblings in one <tbody>; hierarchy is carried by aria-level +
 * data-parent-id, not by nesting (see js/tree-nav.js).
 */

((Drupal, drupalSettings) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};
  const ns = Drupal.menuTreeUi;
  const { rowOf, childRows } = ns.treeNav;

  const TREE = 'table[role="treegrid"]';
  const ROW = 'tr[role="row"]';
  const TOGGLE = '.menu-tree-ui-row__toggle';
  const COLUMN_TOGGLE = '.menu-tree-ui-row__column-toggle';

  class MenuTreeElement extends HTMLElement {
    constructor() {
      super();
      this.onClick = this.onClick.bind(this);
      this.onKeyDown = this.onKeyDown.bind(this);
      this.onOutsidePointer = this.onOutsidePointer.bind(this);
    }

    /**
     * Upgrades the server-rendered tree when inserted into the DOM.
     */
    connectedCallback() {
      const tree = this.querySelector(TREE);
      if (!tree) {
        // eslint-disable-next-line no-console
        console.warn(
          '[menu-tree] Missing <table role="treegrid"> — skipping upgrade.',
        );
        return;
      }
      this.tree = tree;
      const { childrenUrl } = this.dataset;
      if (!childrenUrl) {
        // eslint-disable-next-line no-console
        console.warn(
          '[menu-tree] Missing data-children-url — skipping upgrade.',
        );
        return;
      }
      this.moveUrl = drupalSettings.menuTreeUi?.moveUrl;
      this.columnUpdateUrl = drupalSettings.menuTreeUi?.columnUpdateUrl;

      this.rowWindow = new ns.RowWindow(this);
      this.tree.querySelectorAll(ROW).forEach((row) => {
        this.rowWindow.observe(row);
      });
      this.expand = new ns.ExpandManager(
        childrenUrl,
        this.rowWindow,
        this.tree,
      );

      // One drop indicator, shared by pointer drag and keyboard move mode so
      // the two never render into separate live regions. The host owns its
      // lifecycle (destroyed in disconnectedCallback).
      this.indicator = new ns.drag.DropIndicator(this);
      if (this.moveUrl) {
        this.moveMode = new ns.MoveModeController(
          this.tree,
          this.moveUrl,
          this.expand,
          this.indicator,
          () => this.csrfToken,
        );
      }

      this.tree.addEventListener('click', this.onClick);
      this.tree.addEventListener('keydown', this.onKeyDown);
      document.addEventListener('mousedown', this.onOutsidePointer);
      this.jumpHandler = ns.attachJumpClickHandler({
        tree: this.tree,
        expand: this.expand,
        moveMode: this.moveMode,
      });
      this.setupHelpDialog();
      const container = this.closest('.menu-tree-ui');
      if (container) {
        this.detachSearchPanel = ns.attachSearchPanel(container);
      }
      this.autoExpandSmall();

      // Fetch the CSRF token once, then wire up drag-and-drop and keyboard
      // reorder. Drupal's CSRF check accepts the token in the X-CSRF-Token
      // header, keeping it out of URLs (and therefore server access logs). We
      // defer DragController creation until the token is available so it is
      // never passed as undefined.
      const base = drupalSettings.path?.baseUrl ?? '/';
      fetch(`${base}session/token`, { credentials: 'same-origin' })
        .then((r) => r.text())
        .then((token) => this.setupDrag(token))
        .catch(() => this.setupDrag(undefined));
    }

    /**
     * Expands an extra level on initial load for very small menus.
     *
     * A menu with only one or two top-level items (e.g. Administration) opens
     * fully collapsed otherwise, hiding all of its structure. Expanding a
     * bounded extra level keeps the lazy-load posture — a couple of /children
     * fetches, never the whole tree.
     *
     * @return {Promise<void>}
     *   Resolves once the bounded auto-expand finishes.
     */
    async autoExpandSmall() {
      const threshold = drupalSettings.menuTreeUi?.autoExpandThreshold ?? 2;
      const topRows = Array.from(this.tree.querySelectorAll(ROW));
      if (topRows.length === 0 || topRows.length > threshold) {
        return;
      }
      const topParents = topRows.filter(
        (row) => row.dataset.hasChildren === 'true',
      );
      await Promise.all(topParents.map((row) => this.expand.expand(row)));

      // A single top-level item gets one more level when that level is small
      // too, so a one-root menu shows more than just its root's children.
      if (topRows.length === 1 && topParents.length === 1) {
        const children = childRows(topParents[0]);
        if (children.length <= threshold) {
          const childParents = children.filter(
            (row) => row.dataset.hasChildren === 'true',
          );
          await Promise.all(childParents.map((row) => this.expand.expand(row)));
        }
      }
    }

    /**
     * Tears everything down when removed from the DOM.
     */
    disconnectedCallback() {
      this.rowWindow?.disconnect();
      this.moveMode?.cancel();
      this.drag?.detach();
      this.tree?.removeEventListener('click', this.onClick);
      this.tree?.removeEventListener('keydown', this.onKeyDown);
      document.removeEventListener('mousedown', this.onOutsidePointer);
      if (this.jumpHandler) {
        document.removeEventListener('click', this.jumpHandler);
      }
      if (this.helpToggle) {
        this.helpToggle.removeEventListener('click', this.openHelp);
      }
      this.detachSearchPanel?.();
      this.indicator?.destroy();
    }

    /**
     * Wires the "Keyboard commands" trigger to its <dialog>.
     *
     * The trigger and dialog live in the page template (above the tree, so
     * screen readers reach them early); the close button uses a native
     * <form method="dialog">, so only opening needs JS. Degrades to a no-op
     * where <dialog>.showModal isn't supported.
     */
    setupHelpDialog() {
      const container = this.closest('.menu-tree-ui');
      const toggle = container?.querySelector('.menu-tree-ui__help-toggle');
      const dialog = container?.querySelector('.menu-tree-ui__help-dialog');
      if (!toggle || !dialog || typeof dialog.showModal !== 'function') {
        return;
      }
      this.helpToggle = toggle;
      this.openHelp = () => dialog.showModal();
      toggle.addEventListener('click', this.openHelp);
    }

    /**
     * Cancels an active keyboard move on a pointer press outside the component.
     *
     * @param {MouseEvent} event
     *   The mousedown event.
     */
    onOutsidePointer(event) {
      // A keyboard move is not cancelled by focus leaving the tree: carrying the
      // row blurs it transiently, and searching for a destination moves focus to
      // the search field (the move must survive both). Instead, a pointer press
      // genuinely outside the component cancels it; Escape and a successful drop
      // are the other exits.
      if (!this.moveMode?.isActive() || !(event.target instanceof Node)) {
        return;
      }
      const container = this.closest('.menu-tree-ui');
      if (container && !container.contains(event.target)) {
        this.moveMode.cancel();
      }
    }

    /**
     * Stores the CSRF token and creates the drag controller.
     *
     * @param {string} [token]
     *   The CSRF token, or undefined if the fetch failed.
     */
    setupDrag(token) {
      this.csrfToken = token;
      if (this.moveUrl) {
        this.drag = new ns.drag.DragController(
          this.tree,
          this.moveUrl,
          this.expand,
          token,
          this.indicator,
          () => this.moveMode?.cancel(),
          (row) => this.pickUpRow(row),
        );
        this.drag.attach();
      } else {
        // eslint-disable-next-line no-console
        console.warn(
          '[menu-tree] drupalSettings.menuTreeUi.moveUrl missing — drag disabled.',
        );
      }
    }

    /**
     * Picks a row up into keyboard move mode from a mouse tap on its handle.
     *
     * Lets a mouse user start a move the same way Space does: pick the row up,
     * click a destination row to set the drop position, then confirm with the
     * keyboard. A tap while a move is already running is ignored here — the
     * tree's click handler positions the cursor instead.
     *
     * @param {HTMLElement} row
     *   The tapped row.
     */
    pickUpRow(row) {
      if (!this.moveMode || this.moveMode.isActive()) {
        return;
      }
      this.moveMode.tryGrab(row);
    }

    /**
     * Delegated click handler for toggles and column switches.
     *
     * @param {MouseEvent} event
     *   The click event.
     */
    onClick(event) {
      const { target } = event;
      if (!(target instanceof Element)) {
        return;
      }

      // While a row is grabbed, a click on another row sets the drop cursor
      // there (mouse + keyboard combined). Clicks on row controls fall through
      // so a parent can still be expanded to aim inside it.
      if (this.moveMode?.isActive() && this.moveMode.handlePointer(event)) {
        event.preventDefault();
        return;
      }

      const columnToggle = target.closest(COLUMN_TOGGLE);
      if (columnToggle) {
        event.preventDefault();
        this.handleColumnToggle(columnToggle);
        return;
      }

      const toggle = target.closest(TOGGLE);
      if (!toggle) {
        return;
      }
      const row = toggle.closest(ROW);
      if (!row || row.dataset.hasChildren !== 'true') {
        return;
      }
      event.preventDefault();
      this.expand.toggle(row);
    }

    /**
     * Optimistically toggles a column switch and persists it.
     *
     * @param {HTMLButtonElement} btn
     *   The toggled switch button.
     *
     * @return {Promise<void>}
     *   Resolves once the update round-trips (or is reverted).
     */
    async handleColumnToggle(btn) {
      if (!this.columnUpdateUrl) {
        return;
      }
      const { columnId } = btn.dataset;
      const { linkId } = btn.dataset;
      if (!columnId || !linkId) {
        return;
      }

      const currentChecked = btn.getAttribute('aria-checked') === 'true';
      const newValue = !currentChecked;

      // Optimistic update.
      btn.setAttribute('aria-checked', String(newValue));
      const sr = btn.querySelector('.visually-hidden');
      if (sr) {
        sr.textContent = newValue ? 'On' : 'Off';
      }

      try {
        const response = await fetch(this.columnUpdateUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': this.csrfToken ?? '',
          },
          credentials: 'same-origin',
          body: JSON.stringify({
            column_id: columnId,
            link_id: linkId,
            value: newValue,
          }),
        });
        const data = await response.json();
        if (!data.ok) {
          // Revert on server rejection.
          btn.setAttribute('aria-checked', String(currentChecked));
          if (sr) {
            sr.textContent = currentChecked ? 'On' : 'Off';
          }
          if (Drupal.announce) {
            Drupal.announce(data.message ?? 'Update failed.', 'assertive');
          }
        }
      } catch (e) {
        btn.setAttribute('aria-checked', String(currentChecked));
        if (sr) {
          sr.textContent = currentChecked ? 'On' : 'Off';
        }
        if (Drupal.announce) {
          Drupal.announce('Update failed.', 'assertive');
        }
      }
    }

    /**
     * Delegated keydown handler for tree navigation and reorder.
     *
     * @param {KeyboardEvent} event
     *   The keydown event.
     */
    onKeyDown(event) {
      // While a row is picked up, move mode owns every key.
      if (this.moveMode?.isActive()) {
        if (this.moveMode.handleKey(event)) {
          event.preventDefault();
        }
        return;
      }

      // Space grabs a movable row to start a keyboard move; on a non-movable
      // parent it falls through to the expand toggle (its pre-move-mode role).
      if (event.key === ' ') {
        const row = rowOf(event.target);
        if (row) {
          if (this.moveMode && this.moveMode.tryGrab(row)) {
            event.preventDefault();
            return;
          }
          if (row.dataset.hasChildren === 'true') {
            event.preventDefault();
            this.expand.toggle(row);
          }
          return;
        }
      }

      ns.handleKeyDown(event, {
        tree: this.tree,
        expand: this.expand,
        reorder: (row, direction) => {
          if (!this.moveUrl) {
            return false;
          }
          ns.reorderSibling(row, direction, this.moveUrl, this.csrfToken);
          return true;
        },
      });
    }
  }

  if (!customElements.get('menu-tree')) {
    customElements.define('menu-tree', MenuTreeElement);
  }
})(Drupal, drupalSettings);
