/**
 * @file
 * Pointer-event drag controller for a treegrid.
 *
 * Owns the state machine (idle / armed / dragging) and dispatches to:
 *  - intent.js for zone math
 *  - indicator.js for visual feedback
 *  - commit.js for the optimistic DOM move + server commit
 *
 * Drag threshold of 5 px so a tap on the handle doesn't start a drag — this
 * matters more on touch (every tap has some pointer drift) than mouse.
 *
 * The consumer supplies its CSS class/selector names (handle, locked, dragging)
 * and the commit callbacks via the options object.
 *
 * Exposes Drupal.treegridReorder.DragController.
 */

((Drupal) => {
  Drupal.treegridReorder = Drupal.treegridReorder || {};
  const { commitMove, computeIntent, sameIntent } = Drupal.treegridReorder;
  const { descendantRows } = Drupal.treegrid.treeNav;

  const DRAG_THRESHOLD_PX = 5;
  const HOVER_EXPAND_MS = 1000;
  const ROW = 'tr[role="row"]';

  class DragController {
    /**
     * @param {HTMLTableElement} tree
     *   The root treegrid element.
     * @param {string} moveUrl
     *   The move endpoint.
     * @param {object} expand
     *   The treegrid ExpandManager, for hover-to-expand and commit refreshes.
     * @param {string} csrfToken
     *   The X-CSRF-Token value passed to commit, or ''.
     * @param {Drupal.treegridReorder.DropIndicator} indicator
     *   The shared drop indicator (owned by the host, also used by move mode).
     * @param {function()} onGestureStart
     *   Called when a drag arms, so the host can cancel any active keyboard
     *   move mode before the pointer gesture takes over the shared indicator.
     * @param {function(HTMLElement)} onTap
     *   Called when a handle is clicked without dragging, so a mouse user can
     *   pick a row up into keyboard move mode (click to pick up, click a
     *   destination, confirm with the keyboard).
     * @param {object} options
     *   { handleSelector, lockedClass, draggingClass, applyParentMetadata,
     *   onCommitted } — the consumer's CSS names and the commit callbacks.
     */
    constructor(
      tree,
      moveUrl,
      expand,
      csrfToken,
      indicator,
      onGestureStart,
      onTap,
      options,
    ) {
      this.tree = tree;
      this.moveUrl = moveUrl;
      this.expand = expand;
      this.csrfToken = csrfToken;
      this.indicator = indicator;
      this.onGestureStart = onGestureStart;
      this.onTap = onTap;
      this.options = options;
      this.handleSelector = options.handleSelector;
      this.lockedClass = options.lockedClass;
      this.draggingClass = options.draggingClass;

      this.pointerId = null;
      this.moved = null;
      this.activeHandle = null;
      this.dragging = false;
      this.startX = 0;
      this.startY = 0;
      this.intent = null;
      this.hoverTarget = null;
      this.hoverTimer = null;

      this.onPointerDown = this.onPointerDown.bind(this);
      this.onPointerMove = this.onPointerMove.bind(this);
      this.onPointerUp = this.onPointerUp.bind(this);
      this.onPointerCancel = this.onPointerCancel.bind(this);
    }

    /**
     * Binds the persistent pointerdown listener on the tree.
     */
    attach() {
      this.tree.addEventListener('pointerdown', this.onPointerDown);
    }

    /**
     * Removes listeners and tears down the indicator.
     */
    detach() {
      this.tree.removeEventListener('pointerdown', this.onPointerDown);
      this.cleanup();
      // The host owns the shared indicator's lifecycle, so only clear it here.
      this.indicator.clear();
    }

    /**
     * Arms a drag gesture on pointerdown over an unlocked handle.
     *
     * @param {PointerEvent} event
     *   The pointerdown event.
     */
    onPointerDown(event) {
      if (this.pointerId !== null) {
        // A gesture is already in progress; ignore a second concurrent
        // pointer (e.g. multi-touch) rather than clobbering the first.
        return;
      }
      if (event.button !== undefined && event.button !== 0) {
        return;
      }
      const { target } = event;
      if (!(target instanceof Element)) {
        return;
      }
      const handle = target.closest(this.handleSelector);
      if (!handle || handle.classList.contains(this.lockedClass)) {
        return;
      }
      const row = handle.closest(ROW);
      if (!row) {
        return;
      }

      this.moved = row;
      this.activeHandle = handle;
      this.pointerId = event.pointerId;
      this.startX = event.clientX;
      this.startY = event.clientY;
      this.dragging = false;

      handle.setPointerCapture(event.pointerId);
      handle.addEventListener('pointermove', this.onPointerMove);
      handle.addEventListener('pointerup', this.onPointerUp);
      handle.addEventListener('pointercancel', this.onPointerCancel);
      event.preventDefault();
    }

    /**
     * Tracks pointer movement, starting the drag past the threshold.
     *
     * @param {PointerEvent} event
     *   The pointermove event.
     */
    onPointerMove(event) {
      if (event.pointerId !== this.pointerId || !this.moved) {
        return;
      }
      if (!this.dragging) {
        const dx = event.clientX - this.startX;
        const dy = event.clientY - this.startY;
        if (dx * dx + dy * dy < DRAG_THRESHOLD_PX * DRAG_THRESHOLD_PX) {
          return;
        }
        this.dragging = true;
        // A pointer drag is taking over; cancel any active keyboard move so the
        // two gestures never share the one drop indicator at the same time. A
        // mere press-and-release (a tap) never reaches here, so it can pick a
        // row up into move mode instead (see onPointerUp).
        this.onGestureStart?.();
        this.moved.classList.add(this.draggingClass);
      }
      this.updateIntent(event.clientX, event.clientY);
    }

    /**
     * Commits the move on pointerup if a drag was in progress.
     *
     * @param {PointerEvent} event
     *   The pointerup event.
     */
    onPointerUp(event) {
      if (event.pointerId !== this.pointerId) {
        return;
      }
      if (this.dragging && this.intent && this.moved) {
        const row = this.moved;
        const { intent } = this;
        // Snapshot before cleanup, then commit asynchronously.
        this.cleanup();
        commitMove(
          row,
          intent,
          this.moveUrl,
          this.expand,
          this.csrfToken,
          this.options,
        );
      } else {
        // A tap (press and release without crossing the drag threshold): pick
        // the row up into keyboard move mode rather than starting a drag. A
        // gesture that did cross the threshold but ends up with no valid
        // intent (released over the moved row itself, its subtree, or off
        // any row) is a cancelled drag, not a tap, and should not pick the
        // row up.
        const row = this.moved;
        const wasTap = !this.dragging;
        this.cleanup();
        if (wasTap && row) {
          this.onTap?.(row);
        }
      }
    }

    /**
     * Bails out cleanly on pointercancel.
     *
     * @param {PointerEvent} event
     *   The pointercancel event.
     */
    onPointerCancel(event) {
      if (event.pointerId !== this.pointerId) {
        return;
      }
      this.cleanup();
    }

    /**
     * Recomputes and renders the drop intent for a pointer position.
     *
     * @param {number} x
     *   The pointer clientX.
     * @param {number} y
     *   The pointer clientY.
     */
    updateIntent(x, y) {
      if (!this.moved) {
        return;
      }
      const elem = document.elementFromPoint(x, y);
      const target = elem?.closest(ROW) ?? null;
      if (
        !target ||
        target === this.moved ||
        descendantRows(this.moved).includes(target)
      ) {
        this.intent = null;
        this.indicator.clear();
        this.clearHoverExpand();
        return;
      }
      this.updateHoverExpand(target);
      const intent = computeIntent(target, y);
      if (sameIntent(this.intent, intent)) {
        return;
      }
      this.intent = intent;
      this.indicator.render(intent);
      this.indicator.announce(intent);
    }

    /**
     * Arms a 1-second timer to auto-expand a hovered collapsed parent.
     *
     * Restarts the timer whenever the pointer enters a different collapsed
     * parent. If the user moves off, the parent is left alone — same UX as
     * file-explorer drag-hover-to-open.
     *
     * @param {HTMLElement} target
     *   The row currently under the pointer.
     */
    updateHoverExpand(target) {
      const isCollapsedParent =
        target.dataset.hasChildren === 'true' &&
        target.getAttribute('aria-expanded') !== 'true';
      if (!isCollapsedParent) {
        this.clearHoverExpand();
        return;
      }
      if (this.hoverTarget === target) {
        return;
      }
      this.clearHoverExpand();
      this.hoverTarget = target;
      this.hoverTimer = window.setTimeout(() => {
        const row = this.hoverTarget;
        this.hoverTarget = null;
        this.hoverTimer = null;
        if (row) {
          this.expand.expand(row);
        }
      }, HOVER_EXPAND_MS);
    }

    /**
     * Cancels any pending hover-to-expand timer.
     */
    clearHoverExpand() {
      if (this.hoverTimer !== null) {
        window.clearTimeout(this.hoverTimer);
        this.hoverTimer = null;
      }
      this.hoverTarget = null;
    }

    /**
     * Resets all gesture state and removes per-gesture listeners.
     */
    cleanup() {
      this.clearHoverExpand();
      if (this.activeHandle && this.pointerId !== null) {
        this.activeHandle.removeEventListener(
          'pointermove',
          this.onPointerMove,
        );
        this.activeHandle.removeEventListener('pointerup', this.onPointerUp);
        this.activeHandle.removeEventListener(
          'pointercancel',
          this.onPointerCancel,
        );
        if (this.activeHandle.hasPointerCapture(this.pointerId)) {
          this.activeHandle.releasePointerCapture(this.pointerId);
        }
      }
      this.moved?.classList.remove(this.draggingClass);
      this.indicator.clear();
      this.moved = null;
      this.activeHandle = null;
      this.pointerId = null;
      this.dragging = false;
      this.intent = null;
    }
  }

  Drupal.treegridReorder.DragController = DragController;
})(Drupal);
