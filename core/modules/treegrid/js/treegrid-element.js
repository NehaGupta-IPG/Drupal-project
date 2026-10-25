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
 *  - element.ready, a promise that resolves once the element has initialized
 *    (data-treegrid-ready is set at the same moment).
 *  - lifecycle events dispatched on the element: treegrid:connected (detail
 *    { element, tree, reconnected }, bubbles) after init and after each
 *    reconnect, and treegrid:disconnected ({ element }).
 *  - an interaction-handler registry: addClickHandler(fn) / addKeyHandler(fn).
 *    Handlers run in registration order before the element's built-in
 *    navigation; a handler returns true to consume the event. Each call
 *    returns a function that removes the handler.
 *
 * An element initializes once. Moving it in the DOM pauses its listeners and
 * windowing on disconnect and resumes them on reconnect.
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
  const { rowOf, focusRow, initRovingTabindex, childRows, visibleRows } =
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

  // Per-element state kept out of the element's public surface.
  const internals = new WeakMap();

  /**
   * Adds a handler to a list and returns a function that removes it.
   *
   * @param {Array<object>} list
   *   The handler entries.
   * @param {function} handler
   *   The handler.
   *
   * @return {function(): void}
   *   Removes this registration.
   */
  function register(list, handler) {
    const entry = { handler };
    list.push(entry);
    return () => {
      const index = list.indexOf(entry);
      if (index !== -1) {
        list.splice(index, 1);
      }
    };
  }

  class TreegridElement extends HTMLElement {
    constructor() {
      super();
      const state = {
        tree: null,
        initialized: false,
        clickHandlers: [],
        keyHandlers: [],
        queue: new Drupal.treegrid.Queue(),
        onClick: this.onClick.bind(this),
        onKeyDown: this.onKeyDown.bind(this),
      };
      state.ready = new Promise((resolve) => {
        state.resolveReady = resolve;
      });
      internals.set(this, state);
    }

    /**
     * The <table role="treegrid"> inside the element.
     *
     * @return {?HTMLTableElement}
     *   The table, or null before the element has connected.
     */
    get tree() {
      return internals.get(this).tree;
    }

    /**
     * Resolves once the element has its provider and has initialized.
     *
     * @return {Promise<void>}
     *   The ready promise.
     */
    get ready() {
      return internals.get(this).ready;
    }

    /**
     * Adds a click handler that runs before built-in navigation.
     *
     * @param {function(MouseEvent): boolean} handler
     *   Returns true to consume the event (built-in nav is then skipped).
     *
     * @return {function(): void}
     *   Removes the handler.
     */
    addClickHandler(handler) {
      return register(internals.get(this).clickHandlers, handler);
    }

    /**
     * Adds a keydown handler that runs before built-in navigation.
     *
     * @param {function(KeyboardEvent): boolean} handler
     *   Returns true to consume the event.
     *
     * @return {function(): void}
     *   Removes the handler.
     */
    addKeyHandler(handler) {
      return register(internals.get(this).keyHandlers, handler);
    }

    /**
     * Runs work through the element's request queue.
     *
     * Units run one at a time, first in first out. Calling the element's own
     * queued methods from inside a unit waits behind that unit and never
     * finishes; use the unit object's methods there.
     *
     * @param {function(object): *} fn
     *   The work. Receives the unit object.
     *
     * @return {Promise<*>}
     *   Settles with what fn returned or threw.
     */
    exclusive(fn) {
      return internals.get(this).queue.run(fn);
    }

    /**
     * Validates the markup, then initializes, parks or resumes the element.
     */
    connectedCallback() {
      const state = internals.get(this);
      if (state.initialized) {
        this.resume();
        return;
      }
      const tree = this.querySelector(TREE);
      if (!tree) {
        // eslint-disable-next-line no-console
        console.warn(
          '[drupal-treegrid] Missing <table role="treegrid"> — skipping upgrade.',
        );
        return;
      }
      state.tree = tree;
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
     *
     * @internal
     */
    init(provider) {
      const state = internals.get(this);
      state.initialized = true;
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

      this.addListeners();

      // Upgrade server-rendered cell widgets (e.g. dropbuttons) so their
      // focusable controls exist before priming the roving tab order; otherwise
      // a control added by a later attachBehaviors pass would linger in the tab
      // order.
      if (Drupal.attachBehaviors) {
        Drupal.attachBehaviors(this.tree);
      }
      initRovingTabindex(this.tree);
      this.autoExpandSmall();

      this.setAttribute('data-treegrid-ready', '');
      this.dispatchConnected(false);
      state.resolveReady();
    }

    /**
     * Resumes listeners and windowing after the element is moved in the DOM.
     */
    resume() {
      const { tree } = internals.get(this);
      this.rowWindow = new Drupal.treegrid.RowWindow(this);
      visibleRows(tree).forEach((row) => {
        this.rowWindow.observe(row);
      });
      this.addListeners();
      this.dispatchConnected(true);
    }

    /**
     * Adds the element's own delegated listeners to the tree.
     */
    addListeners() {
      const state = internals.get(this);
      state.tree.addEventListener('click', state.onClick);
      state.tree.addEventListener('keydown', state.onKeyDown);
    }

    /**
     * Removes the element's own delegated listeners from the tree.
     */
    removeListeners() {
      const state = internals.get(this);
      state.tree?.removeEventListener('click', state.onClick);
      state.tree?.removeEventListener('keydown', state.onKeyDown);
    }

    /**
     * Announces that the element is initialized or has been reconnected.
     *
     * @param {boolean} reconnected
     *   Whether this follows a DOM move rather than the first init.
     */
    dispatchConnected(reconnected) {
      this.dispatchEvent(
        new CustomEvent('treegrid:connected', {
          bubbles: true,
          detail: { element: this, tree: this.tree, reconnected },
        }),
      );
    }

    /**
     * Pauses listeners and windowing when removed from the DOM.
     */
    disconnectedCallback() {
      pending.delete(this);
      if (internals.get(this).initialized) {
        this.removeListeners();
        this.rowWindow?.disconnect();
      }
      this.dispatchEvent(
        new CustomEvent('treegrid:disconnected', { detail: { element: this } }),
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
      const { clickHandlers } = internals.get(this);
      if (clickHandlers.some((entry) => entry.handler(event))) {
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
      const { keyHandlers } = internals.get(this);
      if (keyHandlers.some((entry) => entry.handler(event))) {
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
