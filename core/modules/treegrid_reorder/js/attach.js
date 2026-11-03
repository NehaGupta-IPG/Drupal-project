/**
 * @file
 * The entry point: Drupal.treegridReorder.attach(element, options).
 *
 * Turns reordering on for one <drupal-treegrid>: pointer drag from the handle,
 * the keyboard grab/select move mode, Shift+Arrow sibling reorder, and the
 * single-pointer path (tap the handle, click a row to aim, tap the handle
 * again to drop). The module finds its parts by markers the consumer renders:
 *  - data-movable="true" on a row that can be moved, by any gesture;
 *  - data-treegrid-reorder-handle on the drag handle inside a row;
 *  - data-treegrid-reorder-title on the element holding the row's visible name
 *    (announcements fall back to the row's aria-label).
 *
 * attach() registers its key and click handlers before it returns, whether or
 * not the element is ready, so they run ahead of handlers a consumer registers
 * afterwards. It waits for element.ready only for the rest, then sets
 * data-treegrid-reorder-ready on the element.
 *
 * The listeners the module holds outside the element (a press outside the
 * container cancels a move) come off when the element is disconnected and go
 * back on when it is reconnected.
 */

((Drupal) => {
  Drupal.treegridReorder = Drupal.treegridReorder || {};
  const { DragController, DropIndicator, MoveModeController, reorderSibling } =
    Drupal.treegridReorder;
  const { rowOf } = Drupal.treegrid.treeNav;
  const { isBusy, announceRefusal } = Drupal.treegridReorder;

  const READY = 'data-treegrid-reorder-ready';
  // Elements that keep their own key behavior instead of the row's, e.g. Space
  // on a checkbox or Enter on a link. The expand toggle is exempted: it is a
  // <button> that legitimately takes focus on mouse click and must keep
  // responding to Enter and Space.
  const INTERACTIVE = 'a, button, input, select, textarea';

  /**
   * Turns reordering on for a tree.
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element.
   * @param {object} [options]
   *   Options.
   * @param {string} [options.moveUrl]
   *   The move endpoint. Wins over the element's
   *   data-treegrid-reorder-move-url attribute.
   * @param {function(string): void} [options.applyParentMetadata]
   *   Called with a parent's id after a move's refresh, so the consumer can
   *   update the parent's row (toggle, open state).
   * @param {Element} [options.container]
   *   A pointer press outside it cancels an active move. Defaults to the
   *   element.
   *
   * @return {{isActive: function(): boolean, cancel: function(): void,
   *   aimAt: function(HTMLElement): boolean, detach: function(): void}}
   *   Whether a move is active, cancels it, aims it at a row, and turns
   *   reordering off again.
   */
  Drupal.treegridReorder.attach = (element, options = {}) => {
    const container = options.container ?? element;
    const context = {
      element,
      moveUrl: () =>
        options.moveUrl ?? element.dataset.treegridReorderMoveUrl ?? null,
      applyParentMetadata: options.applyParentMetadata,
    };
    const indicator = new DropIndicator(element);
    const moveMode = new MoveModeController(element, indicator, context);
    let detached = false;

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
      if (moveMode.isActive()) {
        if (moveMode.isGrabbed(row)) {
          moveMode.commit();
        }
        return;
      }
      if (isBusy(element)) {
        announceRefusal(element);
        return;
      }
      moveMode.tryGrab(row);
    };

    const drag = new DragController(
      element,
      indicator,
      context,
      () => moveMode.cancel(),
      onHandleTap,
    );

    const removeClickHandler = element.addClickHandler((event) => {
      if (moveMode.isActive() && moveMode.handlePointer(event)) {
        event.preventDefault();
        return true;
      }
      return false;
    });

    const removeKeyHandler = element.addKeyHandler((event) => {
      // While a row is picked up, move mode owns every key, even one landing
      // on a control: the carried row must keep taking Space/Enter (drop) and
      // Escape (cancel) regardless of where focus sits.
      if (moveMode.isActive()) {
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

      // Space grabs a movable row; if it cannot grab, fall through so treegrid
      // toggles expand on a parent (Space's pre-move-mode role).
      if (event.key === ' ') {
        // Picking up is refused while another change is in the way.
        if (row.dataset.movable === 'true' && isBusy(element)) {
          announceRefusal(element);
          event.preventDefault();
          return true;
        }
        if (moveMode.tryGrab(row)) {
          event.preventDefault();
          return true;
        }
        return false;
      }
      if (
        event.shiftKey &&
        (event.key === 'ArrowUp' || event.key === 'ArrowDown')
      ) {
        reorderSibling(row, event.key === 'ArrowUp' ? 'up' : 'down', context);
        event.preventDefault();
        return true;
      }
      return false;
    });

    // A pointer press genuinely outside the container cancels an active move
    // (focus leaving the tree must not: carrying a row blurs it, and searching
    // moves focus to the search field).
    const onOutsidePointer = (event) => {
      if (!moveMode.isActive() || !(event.target instanceof Node)) {
        return;
      }
      if (!container.contains(event.target)) {
        moveMode.cancel();
      }
    };
    const addPageListeners = () => {
      document.addEventListener('mousedown', onOutsidePointer);
    };
    const removePageListeners = () => {
      document.removeEventListener('mousedown', onOutsidePointer);
    };
    const onDisconnected = () => {
      moveMode.cancel();
      removePageListeners();
    };
    const onConnected = (event) => {
      if (event.detail.reconnected) {
        addPageListeners();
      }
    };
    addPageListeners();
    element.addEventListener('treegrid:disconnected', onDisconnected);
    element.addEventListener('treegrid:connected', onConnected);

    element.ready.then(() => {
      if (detached) {
        return;
      }
      drag.attach();
      element.setAttribute(READY, 'true');
    });

    return {
      isActive: () => moveMode.isActive(),
      cancel: () => moveMode.cancel(),
      aimAt: (row) => moveMode.aimAt(row),
      detach: () => {
        detached = true;
        moveMode.cancel();
        removeClickHandler();
        removeKeyHandler();
        drag.detach();
        removePageListeners();
        element.removeEventListener('treegrid:disconnected', onDisconnected);
        element.removeEventListener('treegrid:connected', onConnected);
        indicator.destroy();
        element.removeAttribute(READY);
      },
    };
  };
})(Drupal);
