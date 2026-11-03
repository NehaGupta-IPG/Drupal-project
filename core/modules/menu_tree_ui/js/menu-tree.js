/**
 * @file
 * menu_tree_ui's features for <drupal-treegrid>.
 *
 * The treegrid spine — lazy expand, row windowing, WAI-ARIA navigation, and the
 * roving-tabindex focus model — lives in the treegrid module's <drupal-treegrid>
 * element. Rows come from the server's menu_tree_ui_row template, on the first
 * page and in every loaded level. This file wires the menu-specific features
 * onto an upgraded element:
 *  - reorder, through treegrid_reorder's attach();
 *  - search jump-to and dismiss;
 *  - the "Keyboard commands" dialog.
 *
 * The column switches are persisted by treegrid's column-update client.
 */

((Drupal, once) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};
  const ns = Drupal.menuTreeUi;

  const ROW = 'tr[role="row"]';

  /**
   * Creates the callback that syncs a parent row's open state for one tree.
   *
   * The treegrid_reorder commit pipeline calls it (as a configured callback)
   * after a move refresh: the children endpoint returns X's children, not X's
   * own metadata, so whether X is still a parent is the one piece of state
   * derived client-side. The row's data-has-children follows its level, and the
   * stylesheet shows the toggle or the spacer from it. Skipped when parentId is
   * '' (root has no enclosing row) or the row isn't in the tree.
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element the callback serves.
   *
   * @return {function(string): void}
   *   The callback, taking the parent plugin ID.
   */
  function createParentMetadataUpdater(element) {
    return (parentId) => {
      if (parentId === '') {
        return;
      }
      const row = element.tree.querySelector(
        `${ROW}[data-id="${CSS.escape(parentId)}"]`,
      );
      if (!row) {
        return;
      }
      if (row.dataset.hasChildren === 'true') {
        row.setAttribute('aria-expanded', 'true');
      } else {
        row.removeAttribute('aria-expanded');
        // On becoming a leaf, remove any stale descendant rows left behind.
        element.clearChildren(row);
      }
    };
  }

  /**
   * Wires menu_tree_ui's page features onto an initialized <drupal-treegrid>.
   *
   * @param {HTMLElement} element
   *   The element, already initialized (element.ready has resolved).
   * @param {object} reorder
   *   What treegridReorder.attach() returned for the element.
   *
   * @return {function(): void}
   *   Removes everything this wired.
   */
  function wireFeatures(element, reorder) {
    const container = element.closest('.menu-tree-ui');

    // Keep the keyboard cursor clear of the sticky action bar: when focusRow()
    // scrolls a focused row into view the browser ignores the sticky overlay,
    // so the rows' scroll-margin (see CSS) must match the bar's height. The bar
    // grows when the unsaved notice wraps above the buttons, so track it live.
    const actionBar = container?.querySelector('.menu-tree-ui__action-bar');
    let stickyObserver = null;
    const observeStickyOffset = () => {
      if (!actionBar || !container || !('ResizeObserver' in window)) {
        return;
      }
      const syncStickyOffset = () =>
        container.style.setProperty(
          '--mtu-sticky-offset',
          `${actionBar.offsetHeight}px`,
        );
      syncStickyOffset();
      stickyObserver = new ResizeObserver(syncStickyOffset);
      stickyObserver.observe(actionBar);
    };

    // "Keyboard commands" dialog. Closing uses a native <form method="dialog">,
    // so only opening needs JS; degrades to a no-op without dialog.showModal.
    const helpToggle = container?.querySelector('.menu-tree-ui__help-toggle');
    const helpDialog = container?.querySelector('.menu-tree-ui__help-dialog');
    let openHelp = null;
    if (
      helpToggle &&
      helpDialog &&
      typeof helpDialog.showModal === 'function'
    ) {
      openHelp = () => helpDialog.showModal();
    }

    // The listeners that live outside the element (document, the search panel,
    // the action bar) come off when the element is disconnected and go back on
    // when it is reconnected.
    let jumpHandler = null;
    let detachSearchPanel = null;
    let detachPaging = null;
    const addPageListeners = () => {
      observeStickyOffset();
      // Search jump-to and dismiss.
      jumpHandler = ns.attachJumpClickHandler({
        tree: element.tree,
        element,
        reorder,
      });
      detachSearchPanel = container ? ns.attachSearchPanel(container) : null;
      // Stream the rest of a truncated top level in as the editor scrolls.
      detachPaging = ns.attachTopLevelPaging(element);
      if (openHelp) {
        helpToggle.addEventListener('click', openHelp);
      }
    };
    const removePageListeners = () => {
      stickyObserver?.disconnect();
      stickyObserver = null;
      if (jumpHandler) {
        document.removeEventListener('click', jumpHandler);
        jumpHandler = null;
      }
      detachSearchPanel?.();
      detachSearchPanel = null;
      detachPaging?.();
      detachPaging = null;
      if (openHelp) {
        helpToggle.removeEventListener('click', openHelp);
      }
    };
    const onConnected = (event) => {
      if (event.detail.reconnected) {
        addPageListeners();
      }
    };
    addPageListeners();
    element.addEventListener('treegrid:disconnected', removePageListeners);
    element.addEventListener('treegrid:connected', onConnected);

    return () => {
      removePageListeners();
      element.removeEventListener('treegrid:disconnected', removePageListeners);
      element.removeEventListener('treegrid:connected', onConnected);
    };
  }

  const SELECTOR = 'drupal-treegrid[data-provider="menu_tree_ui"]';
  // Per tree: the reorder feature and what wireFeatures() set up.
  const features = new WeakMap();

  Drupal.behaviors.menuTreeUi = {
    attach(context) {
      once('menu-tree-ui', SELECTOR, context).forEach(async (element) => {
        // Reordering registers its handlers at once, before this behavior or
        // any other adds its own. A press outside the whole component cancels
        // a move; searching for a destination keeps it.
        const reorder = Drupal.treegridReorder.attach(element, {
          applyParentMetadata: createParentMetadataUpdater(element),
          container: element.closest('.menu-tree-ui'),
        });
        features.set(element, { reorder, teardown: null });
        await element.ready;
        // The tree may have been detached while this waited.
        if (!element.isConnected || !features.has(element)) {
          return;
        }
        features.get(element).teardown = wireFeatures(element, reorder);
      });
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once.remove('menu-tree-ui', SELECTOR, context).forEach((element) => {
        const feature = features.get(element);
        feature?.reorder.detach();
        feature?.teardown?.();
        features.delete(element);
      });
    },
  };
})(Drupal, once);
