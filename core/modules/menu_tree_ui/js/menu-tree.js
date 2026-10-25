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

((Drupal, drupalSettings, once) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};
  const ns = Drupal.menuTreeUi;
  const rr = Drupal.treegridReorder;

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
   * Wires menu_tree_ui's features onto an upgraded <drupal-treegrid>.
   *
   * @param {HTMLElement} element
   *   The element, already initialized (element.ready has resolved).
   */
  function wireFeatures(element) {
    const settings = drupalSettings.menuTreeUi ?? {};
    const { moveUrl } = settings;
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

    // Reordering: drag, move mode, Shift+Arrow and the single-pointer path.
    // A press outside the whole component cancels a move; searching for a
    // destination keeps it.
    const reorder = rr.attach(element, {
      moveUrl,
      applyParentMetadata,
      container,
      // Tell the working-copy action bar a change was recorded so it can
      // reveal the unsaved notice (the bar lives outside the element).
      onCommitted: () =>
        document.dispatchEvent(new CustomEvent('menu-tree-ui:changed')),
    });

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
        moveMode: reorder,
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
    addPageListeners();

    element.addEventListener('treegrid:disconnected', removePageListeners);
    element.addEventListener('treegrid:connected', (event) => {
      if (event.detail.reconnected) {
        addPageListeners();
      }
    });
  }

  Drupal.behaviors.menuTreeUi = {
    attach(context) {
      once(
        'menu-tree-ui',
        'drupal-treegrid[data-provider="menu_tree_ui"]',
        context,
      ).forEach(async (element) => {
        await element.ready;
        if (element.isConnected) {
          wireFeatures(element);
        }
      });
    },
  };
})(Drupal, drupalSettings, once);
