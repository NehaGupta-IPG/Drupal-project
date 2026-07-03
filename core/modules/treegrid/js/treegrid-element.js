/**
 * @file
 * The <drupal-treegrid> custom element: the reusable treegrid spine.
 *
 * Upgrades a server-rendered <table role="treegrid"> into an interactive,
 * lazy-loaded, keyboard-navigable tree. It owns the generic behavior — row
 * windowing, lazy expand/collapse, WAI-ARIA navigation, and the roving-tabindex
 * focus model — and nothing domain-specific.
 *
 * A consumer provides:
 *  - a renderer, registered with Drupal.treegrid.registerProvider(name, {
 *    buildRows, buildRow }) and selected via the element's data-provider
 *    attribute.
 *  - data-children-url: the endpoint returning one level as { parent, items[] }.
 *
 * Features (reorder, columns, search, …) attach without the element knowing
 * about them, through:
 *  - lifecycle events dispatched on the element: treegrid:connected (detail
 *    { element, tree, expand, rowWindow }) and treegrid:disconnect ({ element }).
 *  - an interaction-handler registry: addClickHandler(fn) / addKeyHandler(fn).
 *    Handlers run in registration order before the element's built-in
 *    navigation; a handler returns true to consume the event.
 *
 * No Drupal.behaviors: connectedCallback fires automatically when the element
 * enters the DOM, including htmx-swapped content.
 *
 * DOM contract:
 *   <drupal-treegrid data-provider="…" data-children-url="…">
 *     <table role="treegrid">
 *       <tbody>
 *         <tr role="row" data-id data-parent-id aria-level …>…</tr>
 *       </tbody>
 *     </table>
 *   </drupal-treegrid>
 * Rows are flat siblings; hierarchy is aria-level + data-parent-id (see
 * js/tree-nav.js). The disclosure toggle carries data-treegrid-toggle.
 */

((Drupal) => {
  Drupal.treegrid = Drupal.treegrid || {};
  Drupal.treegrid.providers = Drupal.treegrid.providers || {};
  const { rowOf, focusRow, initRovingTabindex, childRows } =
    Drupal.treegrid.treeNav;
  const { handleKeyDown } = Drupal.treegrid;

  const TREE = 'table[role="treegrid"]';
  const ROW = 'tr[role="row"]';
  const TOGGLE = '[data-treegrid-toggle]';
  const INTERACTIVE =
    'a, button, input, select, textarea, [role="button"], [role="switch"]';

  // Elements that connected before their provider was registered. The treegrid
  // library loads before any consumer's library, so the element is often
  // upgraded before the consumer registers its renderer; registerProvider()
  // adopts them once the provider arrives.
  const pending = new Set();

  /**
   * Initializes an element if its provider is registered, else parks it.
   *
   * @param {TreegridElement} element
   *   The element to initialize.
   */
  function tryInit(element) {
    const provider = Drupal.treegrid.providers[element.dataset.provider];
    if (!provider) {
      pending.add(element);
      return;
    }
    pending.delete(element);
    element.init(provider);
  }

  /**
   * Registers a renderer provider, adopting any element already waiting on it.
   *
   * @param {string} name
   *   The provider name, matched against an element's data-provider.
   * @param {object} provider
   *   The renderer: { buildRows, buildRow }.
   */
  Drupal.treegrid.registerProvider = (name, provider) => {
    Drupal.treegrid.providers[name] = provider;
    [...pending].forEach((element) => {
      if (element.dataset.provider === name) {
        tryInit(element);
      }
    });
  };

  class TreegridElement extends HTMLElement {
    constructor() {
      super();
      this.onClick = this.onClick.bind(this);
      this.onKeyDown = this.onKeyDown.bind(this);
      this.clickHandlers = [];
      this.keyHandlers = [];
    }

    /**
     * Adds a click handler that runs before built-in navigation.
     *
     * @param {function(MouseEvent): boolean} handler
     *   Returns true to consume the event (built-in nav is then skipped).
     */
    addClickHandler(handler) {
      this.clickHandlers.push(handler);
    }

    /**
     * Adds a keydown handler that runs before built-in navigation.
     *
     * @param {function(KeyboardEvent): boolean} handler
     *   Returns true to consume the event.
     */
    addKeyHandler(handler) {
      this.keyHandlers.push(handler);
    }

    /**
     * Validates the markup and initializes (or parks) the element on insert.
     */
    connectedCallback() {
      const tree = this.querySelector(TREE);
      if (!tree) {
        // eslint-disable-next-line no-console
        console.warn(
          '[drupal-treegrid] Missing <table role="treegrid"> — skipping upgrade.',
        );
        return;
      }
      this.tree = tree;
      if (!this.dataset.childrenUrl) {
        // eslint-disable-next-line no-console
        console.warn(
          '[drupal-treegrid] Missing data-children-url — skipping upgrade.',
        );
        return;
      }
      tryInit(this);
    }

    /**
     * Wires the spine once the renderer provider is available.
     *
     * @param {object} provider
     *   The renderer: { buildRows, buildRow }.
     */
    init(provider) {
      this.rowWindow = new Drupal.treegrid.RowWindow(this);
      this.tree.querySelectorAll(ROW).forEach((row) => {
        this.rowWindow.observe(row);
      });
      this.expand = new Drupal.treegrid.ExpandManager(
        this.dataset.childrenUrl,
        this.rowWindow,
        this.tree,
        provider,
      );

      this.tree.addEventListener('click', this.onClick);
      this.tree.addEventListener('keydown', this.onKeyDown);

      // Upgrade server-rendered cell widgets (e.g. dropbuttons) so their
      // focusable controls exist before priming the roving tab order; otherwise
      // a control added by a later attachBehaviors pass would linger in the tab
      // order.
      if (Drupal.attachBehaviors) {
        Drupal.attachBehaviors(this.tree);
      }
      initRovingTabindex(this.tree);
      this.autoExpandSmall();

      this.dispatchEvent(
        new CustomEvent('treegrid:connected', {
          detail: {
            element: this,
            tree: this.tree,
            expand: this.expand,
            rowWindow: this.rowWindow,
          },
        }),
      );
    }

    /**
     * Tears down generic wiring when removed from the DOM.
     */
    disconnectedCallback() {
      pending.delete(this);
      this.tree?.removeEventListener('click', this.onClick);
      this.tree?.removeEventListener('keydown', this.onKeyDown);
      this.rowWindow?.disconnect();
      this.dispatchEvent(
        new CustomEvent('treegrid:disconnect', { detail: { element: this } }),
      );
    }

    /**
     * Delegated click handler: feature handlers first, then built-in nav.
     *
     * @param {MouseEvent} event
     *   The click event.
     */
    onClick(event) {
      const { target } = event;
      if (!(target instanceof Element)) {
        return;
      }
      if (this.clickHandlers.some((handler) => handler(event))) {
        return;
      }
      const toggle = target.closest(TOGGLE);
      if (toggle) {
        const row = toggle.closest(ROW);
        if (row && row.dataset.hasChildren === 'true') {
          event.preventDefault();
          this.expand.toggle(row);
        }
        return;
      }
      // Don't steal focus from an interactive control; a plain click elsewhere
      // on a row moves the cursor there.
      if (target.closest(INTERACTIVE)) {
        return;
      }
      const row = rowOf(target);
      if (row) {
        focusRow(this.tree, row);
      }
    }

    /**
     * Delegated keydown handler: feature handlers first, then built-in nav.
     *
     * @param {KeyboardEvent} event
     *   The keydown event.
     */
    onKeyDown(event) {
      if (this.keyHandlers.some((handler) => handler(event))) {
        return;
      }
      handleKeyDown(event, { tree: this.tree, expand: this.expand });
    }

    /**
     * Expands a bounded extra level on initial load for very small trees.
     *
     * A tree with only one or two top-level items opens fully collapsed
     * otherwise, hiding all of its structure. Expanding a bounded extra level
     * keeps the lazy-load posture — a couple of /children fetches, never the
     * whole tree.
     *
     * @return {Promise<void>}
     *   Resolves once the bounded auto-expand finishes.
     */
    async autoExpandSmall() {
      const parsed = parseInt(this.dataset.autoExpandThreshold, 10);
      const threshold = Number.isNaN(parsed) ? 2 : parsed;
      const topRows = Array.from(this.tree.querySelectorAll(ROW));
      if (topRows.length === 0 || topRows.length > threshold) {
        return;
      }
      const topParents = topRows.filter(
        (row) => row.dataset.hasChildren === 'true',
      );
      await Promise.all(topParents.map((row) => this.expand.expand(row)));

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
  }

  if (!customElements.get('drupal-treegrid')) {
    customElements.define('drupal-treegrid', TreegridElement);
  }
})(Drupal);
