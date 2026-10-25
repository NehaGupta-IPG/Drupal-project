/**
 * @file
 * menu_tree_ui's features for <drupal-treegrid>.
 *
 * The treegrid spine — lazy expand, row windowing, WAI-ARIA navigation, and the
 * roving-tabindex focus model — lives in the treegrid module's <drupal-treegrid>
 * element. The menu row renderer is registered as a provider in row-builder.js.
 * This file wires the menu-specific features onto an upgraded element:
 *  - reorder: pointer drag, the keyboard grab/select move mode, Shift+Arrow
 *    sibling reorder, and the single-pointer commit (tap the handle);
 *  - search jump-to and dismiss;
 *  - the "Keyboard commands" dialog.
 *
 * Features attach through the element's interaction-handler registry
 * (addClickHandler / addKeyHandler) and its lifecycle. The handlers run ahead of
 * the element's built-in navigation and return true to consume an event, which
 * preserves the original dispatch order (move mode, then nav). The column
 * switches are persisted by treegrid's column-update client.
 */

((Drupal, drupalSettings, once) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};
  const ns = Drupal.menuTreeUi;
  const rr = Drupal.treegridReorder;
  const { rowOf } = Drupal.treegrid.treeNav;

  const ROW = 'tr[role="row"]';
  // Elements that must keep their native/authored key behavior instead of the
  // row's, e.g. Enter on the title link or Space on the Enabled checkbox. The
  // expand toggle is exempted: it is a <button> that legitimately takes focus
  // on mouse click and must keep responding to Enter/Space.
  const INTERACTIVE = 'a, button, input, select, textarea';

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

  // The menu's CSS class/selector names and commit callbacks, handed to the
  // generic treegrid_reorder controllers so they carry no menu markup knowledge.
  const REORDER_CONFIG = {
    handleSelector: '.menu-tree-ui-row__handle',
    lockedClass: 'menu-tree-ui-row__handle--locked',
    draggingClass: 'menu-tree-ui-row--dragging',
    grabbedClass: 'menu-tree-ui-row--grabbed',
    lineClass: 'menu-tree-ui-drop-indicator',
    dropAsChildClass: 'menu-tree-ui-row--drop-as-child',
    titleSelector: '.menu-tree-ui-row__title',
    rowControlsSelector:
      '.menu-tree-ui-row__toggle, .menu-tree-ui-row__column, .menu-tree-ui-row__operations',
    flashClass: 'menu-tree-ui-row--flash',
    applyParentMetadata,
    // Tell the working-copy action bar a change was recorded so it can reveal
    // the unsaved notice (the bar lives outside the <drupal-treegrid> element).
    onCommitted: () =>
      document.dispatchEvent(new CustomEvent('menu-tree-ui:changed')),
  };

  /**
   * Wires menu_tree_ui's features onto an upgraded <drupal-treegrid>.
   *
   * @param {HTMLElement} element
   *   The element, already initialized (element.ready has resolved).
   */
  function wireFeatures(element) {
    const { tree } = element;
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

    let csrfToken;
    const getToken = () => csrfToken;

    // One drop indicator, shared by pointer drag and keyboard move mode so the
    // two never render into separate live regions. The host owns its position.
    const indicator = new rr.DropIndicator(element, REORDER_CONFIG);
    const moveMode = moveUrl
      ? new rr.MoveModeController(
          tree,
          moveUrl,
          element,
          indicator,
          getToken,
          REORDER_CONFIG,
        )
      : null;

    /**
     * Handles a tap (mouse, no drag) on a row's drag handle.
     *
     * Idle: picks the row up (the single-pointer counterpart to Space). During a
     * move: tapping the carried item's handle drops it (the single-pointer
     * commit, WCAG 2.5.7); taps on other rows' handles are ignored.
     *
     * @param {HTMLElement} row
     *   The row whose handle was tapped.
     */
    const onHandleTap = (row) => {
      if (!moveMode) {
        return;
      }
      if (moveMode.isActive()) {
        if (moveMode.isGrabbed(row)) {
          moveMode.commit();
        }
        return;
      }
      moveMode.tryGrab(row);
    };

    // Move-mode pointer, ahead of the element's built-in nav.
    element.addClickHandler((event) => {
      if (moveMode?.isActive() && moveMode.handlePointer(event)) {
        event.preventDefault();
        return true;
      }
      return false;
    });

    element.addKeyHandler((event) => {
      // While a row is picked up, move mode owns every key, even one landing
      // on a control — the carried row must keep taking Space/Enter (drop)
      // and Escape (cancel) regardless of where focus sits.
      if (moveMode?.isActive()) {
        if (moveMode.handleKey(event)) {
          event.preventDefault();
        }
        return true;
      }

      const row = rowOf(event.target);
      if (!row) {
        return false;
      }
      // Let a focused in-row control (title link, checkbox, operations
      // button) handle its own keys instead of the row hijacking them.
      const control = event.target !== row && event.target.closest(INTERACTIVE);
      if (control && !control.closest('[data-treegrid-toggle]')) {
        return false;
      }

      // Space grabs a movable row; if it cannot grab, fall through so the spine
      // toggles expand on a parent (Space's pre-move-mode role).
      if (event.key === ' ') {
        if (moveMode && moveMode.tryGrab(row)) {
          event.preventDefault();
          return true;
        }
        return false;
      }
      if (
        event.shiftKey &&
        (event.key === 'ArrowUp' || event.key === 'ArrowDown')
      ) {
        if (moveUrl) {
          rr.reorderSibling(row, event.key === 'ArrowUp' ? 'up' : 'down', {
            moveUrl,
            csrfToken,
            flashClass: REORDER_CONFIG.flashClass,
            onCommitted: REORDER_CONFIG.onCommitted,
            titleSelector: REORDER_CONFIG.titleSelector,
          });
          event.preventDefault();
          return true;
        }
      }
      return false;
    });

    // A pointer press genuinely outside the component cancels an active move
    // (focus leaving the tree must not — carrying a row blurs it, and searching
    // moves focus to the search field).
    const onOutsidePointer = (event) => {
      if (!moveMode?.isActive() || !(event.target instanceof Node)) {
        return;
      }
      if (container && !container.contains(event.target)) {
        moveMode.cancel();
      }
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
      document.addEventListener('mousedown', onOutsidePointer);
      // Search jump-to and dismiss.
      jumpHandler = ns.attachJumpClickHandler({ tree, element, moveMode });
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
      document.removeEventListener('mousedown', onOutsidePointer);
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

    // Drag needs the CSRF token; defer the controller until it resolves, keeping
    // the token out of URLs (and access logs). move-mode reads it lazily
    // through getToken().
    Drupal.treegrid
      .csrfToken(element)
      .then((token) => {
        csrfToken = token;
      })
      .finally(() => {
        if (!moveUrl) {
          // eslint-disable-next-line no-console
          console.warn(
            '[menu-tree] drupalSettings.menuTreeUi.moveUrl missing — drag disabled.',
          );
          return;
        }
        const drag = new rr.DragController(
          tree,
          moveUrl,
          element,
          csrfToken,
          indicator,
          () => moveMode?.cancel(),
          (row) => onHandleTap(row),
          REORDER_CONFIG,
        );
        drag.attach();
        // Reorder is now interactive: the CSRF token has resolved (keyboard and
        // pointer commits need it) and the drag controller is listening. Tests
        // wait on this before dispatching a move so they do not race the
        // deferred wiring.
        element.dataset.treegridReorderReady = 'true';
      });

    element.addEventListener('treegrid:disconnected', () => {
      moveMode?.cancel();
      removePageListeners();
    });
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
