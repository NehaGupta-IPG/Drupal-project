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
 *  - the column switches;
 *  - search jump-to and dismiss;
 *  - the "Keyboard commands" dialog.
 *
 * Features attach through the element's interaction-handler registry
 * (addClickHandler / addKeyHandler) and its lifecycle. The handlers run ahead of
 * the element's built-in navigation and return true to consume an event, which
 * preserves the original dispatch order (move mode, then columns, then nav).
 */

((Drupal, drupalSettings, once) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};
  const ns = Drupal.menuTreeUi;
  const { rowOf } = Drupal.treegrid.treeNav;
  const { announce } = Drupal.treegrid;

  const COLUMN_TOGGLE = '.menu-tree-ui-row__column-toggle';

  /**
   * Optimistically toggles a column switch and persists it.
   *
   * @param {HTMLButtonElement} btn
   *   The toggled switch button.
   * @param {string} url
   *   The column-update endpoint.
   * @param {function(): (string|undefined)} getToken
   *   Returns the current CSRF token.
   *
   * @return {Promise<void>}
   *   Resolves once the update round-trips (or is reverted).
   */
  async function handleColumnToggle(btn, url, getToken) {
    if (!url) {
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
      sr.textContent = newValue ? Drupal.t('On') : Drupal.t('Off');
    }

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': getToken() ?? '',
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
          sr.textContent = currentChecked ? Drupal.t('On') : Drupal.t('Off');
        }
        announce(data.message ?? Drupal.t('Update failed.'), 'assertive');
      }
    } catch (e) {
      btn.setAttribute('aria-checked', String(currentChecked));
      if (sr) {
        sr.textContent = currentChecked ? Drupal.t('On') : Drupal.t('Off');
      }
      announce(Drupal.t('Update failed.'), 'assertive');
    }
  }

  /**
   * Wires menu_tree_ui's features onto an upgraded <drupal-treegrid>.
   *
   * @param {HTMLElement} element
   *   The element, already connected (element.tree / element.expand are set).
   */
  function wireFeatures(element) {
    const { tree, expand } = element;
    if (!tree || !expand) {
      return;
    }
    const settings = drupalSettings.menuTreeUi ?? {};
    const { moveUrl, columnUpdateUrl } = settings;
    const container = element.closest('.menu-tree-ui');

    let csrfToken;
    let drag = null;
    const getToken = () => csrfToken;

    // One drop indicator, shared by pointer drag and keyboard move mode so the
    // two never render into separate live regions. The host owns its position.
    const indicator = new ns.drag.DropIndicator(element);
    const moveMode = moveUrl
      ? new ns.MoveModeController(tree, moveUrl, expand, indicator, getToken)
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

    // Move-mode pointer + column switch, ahead of the element's built-in nav.
    element.addClickHandler((event) => {
      if (moveMode?.isActive() && moveMode.handlePointer(event)) {
        event.preventDefault();
        return true;
      }
      const columnToggle = event.target.closest(COLUMN_TOGGLE);
      if (columnToggle) {
        event.preventDefault();
        handleColumnToggle(columnToggle, columnUpdateUrl, getToken);
        return true;
      }
      return false;
    });

    element.addKeyHandler((event) => {
      // While a row is picked up, move mode owns every key.
      if (moveMode?.isActive()) {
        if (moveMode.handleKey(event)) {
          event.preventDefault();
        }
        return true;
      }
      // Space grabs a movable row; if it cannot grab, fall through so the spine
      // toggles expand on a parent (Space's pre-move-mode role).
      if (event.key === ' ') {
        const row = rowOf(event.target);
        if (row && moveMode && moveMode.tryGrab(row)) {
          event.preventDefault();
          return true;
        }
        return false;
      }
      if (
        event.shiftKey &&
        (event.key === 'ArrowUp' || event.key === 'ArrowDown')
      ) {
        const row = rowOf(event.target);
        if (row && moveUrl) {
          ns.reorderSibling(
            row,
            event.key === 'ArrowUp' ? 'up' : 'down',
            moveUrl,
            csrfToken,
          );
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
    document.addEventListener('mousedown', onOutsidePointer);

    // Search jump-to and dismiss.
    const jumpHandler = ns.attachJumpClickHandler({ tree, expand, moveMode });
    const detachSearchPanel = container
      ? ns.attachSearchPanel(container)
      : null;

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
      helpToggle.addEventListener('click', openHelp);
    }

    // Drag needs the CSRF token; defer the controller until it resolves, keeping
    // the token out of URLs (and access logs). move-mode and the column toggle
    // read it lazily through getToken().
    const base = drupalSettings.path?.baseUrl ?? '/';
    fetch(`${base}session/token`, { credentials: 'same-origin' })
      .then((response) => response.text())
      .then((token) => {
        csrfToken = token;
      })
      .catch(() => {
        csrfToken = undefined;
      })
      .finally(() => {
        if (!moveUrl) {
          // eslint-disable-next-line no-console
          console.warn(
            '[menu-tree] drupalSettings.menuTreeUi.moveUrl missing — drag disabled.',
          );
          return;
        }
        drag = new ns.drag.DragController(
          tree,
          moveUrl,
          expand,
          csrfToken,
          indicator,
          () => moveMode?.cancel(),
          (row) => onHandleTap(row),
        );
        drag.attach();
      });

    element.addEventListener(
      'treegrid:disconnect',
      () => {
        moveMode?.cancel();
        drag?.detach();
        document.removeEventListener('mousedown', onOutsidePointer);
        if (jumpHandler) {
          document.removeEventListener('click', jumpHandler);
        }
        detachSearchPanel?.();
        if (helpToggle && openHelp) {
          helpToggle.removeEventListener('click', openHelp);
        }
        indicator.destroy();
      },
      { once: true },
    );
  }

  Drupal.behaviors.menuTreeUi = {
    attach(context) {
      once(
        'menu-tree-ui',
        'drupal-treegrid[data-provider="menu_tree_ui"]',
        context,
      ).forEach(wireFeatures);
    },
  };
})(Drupal, drupalSettings, once);
