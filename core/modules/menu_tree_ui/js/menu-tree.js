/**
 * @file
 * menu_tree_ui's features for <drupal-treegrid>.
 *
 * The treegrid spine — lazy expand, row windowing, WAI-ARIA navigation, and the
 * roving-tabindex focus model — lives in the treegrid module's <drupal-treegrid>
 * element. The menu row renderer is registered as a provider in row-builder.js.
 * This file wires the menu-specific features onto an upgraded element:
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
   * Syncs a parent row's "parent or leaf" attributes to its current children.
   *
   * The treegrid_reorder commit pipeline calls this (as a configured callback)
   * after a move refresh: /children?parent=X returns X's children, not X's own
   * metadata, so this is the one piece of state derived client-side. It is
   * menu-specific — it rebuilds the disclosure toggle and reads menu row markup
   * — so it stays here rather than in the generic module. Skipped when parentId
   * is '' (root has no enclosing row) or the row isn't in the DOM.
   *
   * @param {string} parentId
   *   The parent plugin ID.
   */
  function applyParentMetadata(parentId) {
    if (parentId === '') {
      return;
    }
    const row = document.querySelector(
      `${ROW}[data-id="${CSS.escape(parentId)}"]`,
    );
    if (!row) {
      return;
    }
    const hasChildrenNow = row.dataset.hasChildren === 'true';

    const name = row.querySelector('.menu-tree-ui-row__name');
    if (!name) {
      return;
    }
    const existing = name.querySelector(':scope > .menu-tree-ui-row__toggle');

    if (hasChildrenNow) {
      row.setAttribute('aria-expanded', 'true');
      if (!existing || existing.tagName.toLowerCase() !== 'button') {
        const title =
          name.querySelector('.menu-tree-ui-row__title')?.textContent?.trim() ??
          '';
        const button = ns.buildToggle({ has_children: true, title });
        if (existing) {
          existing.replaceWith(button);
        } else {
          name.prepend(button);
        }
      }
    } else {
      row.removeAttribute('aria-expanded');
      if (existing && existing.tagName.toLowerCase() === 'button') {
        existing.replaceWith(ns.buildToggle({ has_children: false }));
      }
      // On becoming a leaf, remove any stale descendant rows left behind.
      row.closest('drupal-treegrid')?.clearChildren(row);
    }
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
          applyParentMetadata,
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
