/**
 * @file
 * Keyboard grab/select move mode for the menu tree.
 *
 * The keyboard counterpart to pointer drag. Space picks up the focused row;
 * the arrow keys move a drop position through the tree; Enter drops; Escape
 * cancels. It commits through the SAME commitMove() path as drag, so both
 * produce identical server moves and announcements.
 *
 * Position model. Stops are precomputed in visual (pre-order) order:
 *  - "child" of a row   - a filled BOX on the row    - drop as its FIRST child.
 *  - "before"/"after"   - an indented LINE at the row - drop as a sibling above
 *                         the first row / below a row, indented to that row's
 *                         depth. "after" sits at the bottom of the row's whole
 *                         subtree, so at an outdent boundary the stacked
 *                         "after ancestor" lines differ only by indentation -
 *                         which is exactly what disambiguates the levels.
 * A row's "after" stop is emitted after its subtree, so Up/Down read top to
 * bottom. Right nests onto the current row, opening it if it is a closed parent
 * so its children become navigable; Left closes it. The grabbed row keeps focus
 * throughout; each stop is announced via the shared DropIndicator's live region
 * and mirrored on the host as data-mtu-drop. On commit, commitMove focuses the
 * landed row.
 *
 * Exposes Drupal.menuTreeUi.MoveModeController.
 */

((Drupal) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};
  const { commitMove, sameIntent, resolveNewParentId, resolveBeforeId } =
    Drupal.menuTreeUi.drag;
  const { descendantRows, lastDescendantRow, nextSiblingRow, visibleRows } =
    Drupal.menuTreeUi.treeNav;

  const CLASS_GRABBED = 'menu-tree-ui-row--grabbed';
  const CLASS_CHILD = 'menu-tree-ui-row--drop-as-child';

  /**
   * Announces a message through Drupal's live-region announcer.
   *
   * @param {string} message
   *   The message.
   * @param {('polite'|'assertive')} [priority]
   *   The live-region priority.
   */
  function announce(message, priority) {
    if (Drupal.announce) {
      Drupal.announce(message, priority);
    }
  }

  /**
   * Reads a row's own title text.
   *
   * @param {HTMLElement} row
   *   The row.
   *
   * @return {string}
   *   The trimmed title, or ''.
   */
  function titleOf(row) {
    return (
      row.querySelector('.menu-tree-ui-row__title')?.textContent?.trim() ?? ''
    );
  }

  /**
   * Describes a prospective drop, for the grab announcement.
   *
   * @param {object} intent
   *   The DropIntent.
   *
   * @return {string}
   *   A phrase like "before X", "after X", or "as a child of X".
   */
  function describe(intent) {
    const title = titleOf(intent.target);
    if (intent.kind === 'child') {
      return `as a child of ${title}`;
    }
    if (intent.kind === 'before') {
      return `before ${title}`;
    }
    return `after ${title}`;
  }

  /**
   * Reads the plugin id of a row's following sibling row, or null.
   *
   * @param {HTMLElement} row
   *   The row.
   *
   * @return {?string}
   *   The next sibling's data-id, or null at end-of-list.
   */
  function nextSiblingId(row) {
    return nextSiblingRow(row)?.dataset.id ?? null;
  }

  class MoveModeController {
    /**
     * @param {HTMLTableElement} tree
     *   The root treegrid element.
     * @param {string} moveUrl
     *   The move endpoint.
     * @param {Drupal.menuTreeUi.ExpandManager} expand
     *   The expand manager, for open/close and passed through to commitMove.
     * @param {Drupal.menuTreeUi.drag.DropIndicator} indicator
     *   The shared drop indicator (its live region is reused for speech).
     * @param {function():(string|undefined)} getCsrfToken
     *   Returns the current X-CSRF-Token. Read late because the token may
     *   arrive after construction; keyboard users should not have to wait.
     */
    constructor(tree, moveUrl, expand, indicator, getCsrfToken) {
      this.tree = tree;
      this.moveUrl = moveUrl;
      this.expand = expand;
      this.indicator = indicator;
      this.getCsrfToken = getCsrfToken;

      this.active = false;
      this.grabbed = null;
      this.positions = [];
      this.posIndex = -1;
      this.intent = null;
      this.box = null;
    }

    /**
     * @return {boolean}
     *   Whether a row is currently picked up.
     */
    isActive() {
      return this.active;
    }

    /**
     * Picks up a row to start a keyboard move.
     *
     * @param {HTMLElement} row
     *   The row to grab.
     *
     * @return {boolean}
     *   True if move mode was entered (caller should preventDefault). False if
     *   the row is not movable or there is nowhere to move it - the caller
     *   then falls through to its normal handling (e.g. expand toggle).
     */
    tryGrab(row) {
      if (
        this.active ||
        !row ||
        row.dataset.movable !== 'true' ||
        !row.dataset.id
      ) {
        return false;
      }
      this.grabbed = row;
      this.buildPositions();
      if (this.positions.length === 0) {
        this.grabbed = null;
        announce('There is nowhere to move this item.', 'assertive');
        return false;
      }

      this.active = true;
      row.classList.add(CLASS_GRABBED);
      this.setIntentByIndex(this.findCurrentSlotIndex(), false);

      announce(
        `Picked up ${titleOf(row)}. Will drop ${describe(this.intent)}. ` +
          'Use Up and Down to choose a position, Right to nest inside an item, ' +
          'Enter to drop, or Escape to cancel.',
        'assertive',
      );
      return true;
    }

    /**
     * Handles a keydown while move mode is active.
     *
     * @param {KeyboardEvent} event
     *   The keydown event.
     *
     * @return {boolean}
     *   True if the key was consumed (caller should preventDefault). Tab and
     *   any other key return false so focus can leave (focusout then cancels).
     */
    handleKey(event) {
      switch (event.key) {
        case 'ArrowDown':
          this.step(1);
          return true;
        case 'ArrowUp':
          this.step(-1);
          return true;
        case 'ArrowRight':
          this.enter();
          return true;
        case 'ArrowLeft':
          this.leave();
          return true;
        case 'Enter':
        case ' ':
          this.commit();
          return true;
        case 'Escape':
          this.cancel();
          return true;
        default:
          return false;
      }
    }

    /**
     * Rebuilds the ordered list of drop positions from the current tree.
     *
     * Pre-order walk (skipping the grabbed row and its subtree): one "before"
     * stop at the very top, then for each visible row a "child" stop, its
     * descendants, and finally its "after" stop - so the after-stop sits below
     * the row's whole subtree and the list reads top to bottom. Called on grab
     * and after any open/close that changes which rows are visible.
     */
    buildPositions() {
      const positions = [];
      const { grabbed } = this;
      const skip = new Set([grabbed, ...descendantRows(grabbed)]);
      const rows = visibleRows(this.tree).filter((row) => !skip.has(row));

      // Defer each row's "after" stop until its whole (visible) subtree has
      // been emitted, so Up/Down reads top to bottom and the after-line lands
      // below the descendant run. A stack of pending afters keyed by level
      // flushes any row at the next row's level or shallower before its child
      // stop, and the tail flushes the rest.
      const pending = [];
      const flush = (level) => {
        while (pending.length && pending[pending.length - 1].level >= level) {
          positions.push(pending.pop().stop);
        }
      };

      if (rows.length) {
        positions.push({ kind: 'before', target: rows[0] });
      }
      rows.forEach((row) => {
        const level = Number(row.getAttribute('aria-level'));
        flush(level);
        positions.push({ kind: 'child', target: row });
        pending.push({ level, stop: { kind: 'after', target: row } });
      });
      flush(1);
      this.positions = positions;
    }

    /**
     * Finds the position index representing the grabbed row's current slot.
     *
     * @return {number}
     *   The index, or 0 if no exact match is found.
     */
    findCurrentSlotIndex() {
      const parent = this.grabbed.dataset.parentId ?? '';
      const before = nextSiblingId(this.grabbed);
      const idx = this.positions.findIndex((p) => {
        let pb = resolveBeforeId(p);
        if (pb === this.grabbed.dataset.id) {
          pb = nextSiblingId(this.grabbed);
        }
        return resolveNewParentId(p) === parent && (pb ?? null) === before;
      });
      return idx === -1 ? 0 : idx;
    }

    /**
     * Returns the index of a {kind, target} stop, or -1.
     *
     * @param {string} kind
     *   The intent kind.
     * @param {HTMLElement} target
     *   The target row.
     *
     * @return {number}
     *   The index, or -1.
     */
    indexOfStop(kind, target) {
      return this.positions.findIndex(
        (p) => p.kind === kind && p.target === target,
      );
    }

    /**
     * Moves the cursor up (-1) or down (+1) one stop, clamped at the ends.
     *
     * @param {number} direction
     *   -1 for up, +1 for down.
     */
    step(direction) {
      const i = this.posIndex + direction;
      if (i >= 0 && i < this.positions.length) {
        this.setIntentByIndex(i);
      }
    }

    /**
     * Right-arrow: nest onto the current row, opening it if it is a closed
     * parent so its children become navigable.
     */
    enter() {
      const { target } = this.positions[this.posIndex];
      const childIndex = this.indexOfStop('child', target);
      if (childIndex !== -1) {
        this.setIntentByIndex(childIndex);
      }
      this.openIfCollapsed(target);
    }

    /**
     * Left-arrow: close the current row if it is an open parent.
     */
    leave() {
      const { target } = this.positions[this.posIndex];
      if (
        target.dataset.hasChildren === 'true' &&
        target.getAttribute('aria-expanded') === 'true'
      ) {
        this.expand.collapse(target);
        announce(`Closed ${titleOf(target)}.`);
        this.buildPositions();
        const i = this.indexOfStop('child', target);
        if (i !== -1) {
          this.setIntentByIndex(i);
        }
      }
    }

    /**
     * Opens a collapsed parent, rebuilds positions, and keeps the cursor on it.
     *
     * @param {HTMLElement} target
     *   The row to open.
     *
     * @return {Promise<void>}
     *   Resolves once the children are in DOM and positions are rebuilt.
     */
    async openIfCollapsed(target) {
      if (
        target.dataset.hasChildren !== 'true' ||
        target.getAttribute('aria-expanded') === 'true'
      ) {
        return;
      }
      await this.expand.expand(target);
      // The move may have been committed or cancelled during the fetch.
      if (!this.active || !this.grabbed) {
        return;
      }
      announce(`Opened ${titleOf(target)}.`);
      this.buildPositions();
      const i = this.indexOfStop('child', target);
      if (i !== -1) {
        this.posIndex = i;
      }
    }

    /**
     * Selects the position at an index: stores, highlights, and announces it.
     *
     * @param {number} i
     *   The position index.
     * @param {boolean} [doAnnounce]
     *   Whether to announce the position (the grab path passes false and
     *   announces a fuller message itself).
     */
    setIntentByIndex(i, doAnnounce = true) {
      if (i < 0 || i >= this.positions.length) {
        return;
      }
      this.posIndex = i;
      const intent = this.positions[i];
      if (sameIntent(this.intent, intent)) {
        return;
      }
      this.intent = intent;
      this.renderHighlight(intent);
      if (doAnnounce) {
        this.indicator.announce(intent);
      }
    }

    /**
     * Shows the current intent: a box for "child", an indented line otherwise.
     *
     * @param {object} intent
     *   The DropIntent.
     */
    renderHighlight(intent) {
      this.clearHighlight();
      if (intent.kind === 'child') {
        this.indicator.hideLine();
        intent.target.classList.add(CLASS_CHILD);
        this.box = intent.target;
      } else {
        const geo = this.lineGeometry(intent);
        if (geo) {
          this.indicator.lineAt(geo.top, geo.left, geo.width);
        }
      }
      intent.target.scrollIntoView({ block: 'nearest' });
      this.tree
        .closest('menu-tree')
        ?.setAttribute(
          'data-mtu-drop',
          `${intent.kind}:${intent.target.dataset.id ?? ''}`,
        );
    }

    /**
     * Removes the current box and line, if any.
     */
    clearHighlight() {
      if (this.box) {
        this.box.classList.remove(CLASS_CHILD);
        this.box = null;
      }
      this.indicator.hideLine();
    }

    /**
     * Computes a host-relative line for a "before"/"after" intent.
     *
     * The line aligns with the target row's title (so its indent shows the
     * insertion depth) and sits at the row's top ("before") or the bottom of
     * its whole subtree ("after").
     *
     * @param {object} intent
     *   The DropIntent.
     *
     * @return {?{top: number, left: number, width: number}}
     *   The geometry, or null if the row's content is missing.
     */
    lineGeometry(intent) {
      const host = this.tree.closest('menu-tree');
      const name = intent.target.querySelector('.menu-tree-ui-row__name');
      if (!host || !name) {
        return null;
      }
      const hostRect = host.getBoundingClientRect();
      const nameRect = name.getBoundingClientRect();
      // "after" sits below the target's whole visible subtree, so measure the
      // bottom of its last descendant when expanded; "before" sits at its top.
      const expanded = intent.target.getAttribute('aria-expanded') === 'true';
      const bottomRow = expanded
        ? (lastDescendantRow(intent.target) ?? intent.target)
        : intent.target;
      const y =
        intent.kind === 'before'
          ? intent.target.getBoundingClientRect().top
          : bottomRow.getBoundingClientRect().bottom;
      const pad = parseFloat(getComputedStyle(name).paddingInlineStart) || 0;
      return {
        top: y - hostRect.top,
        left: nameRect.left + pad - hostRect.left,
        width: Math.max(0, nameRect.right - (nameRect.left + pad)),
      };
    }

    /**
     * Commits the move, unless it resolves to the row's current slot (no-op).
     */
    commit() {
      if (!this.intent || !this.grabbed) {
        this.cancel();
        return;
      }
      const { grabbed, intent } = this;
      const movedId = grabbed.dataset.id;

      // No-op guard: resolve the intent the way commitMove will, and compare to
      // the row's current slot. A before_id pointing at the grabbed row itself
      // means "after the row immediately above me" - i.e. the slot it is in.
      const newParentId = resolveNewParentId(intent);
      let beforeId = resolveBeforeId(intent);
      if (beforeId === movedId) {
        beforeId = nextSiblingId(grabbed);
      }
      const currentParentId = grabbed.dataset.parentId ?? '';
      if (
        newParentId === currentParentId &&
        beforeId === nextSiblingId(grabbed)
      ) {
        announce('Move cancelled. The item is already there.', 'assertive');
        this.end();
        return;
      }

      // Tear down before the async commit (commitMove re-finds and focuses the
      // landed row, and announces success or failure itself).
      this.end();
      commitMove(
        grabbed,
        intent,
        this.moveUrl,
        this.expand,
        this.getCsrfToken(),
      );
    }

    /**
     * Cancels an in-progress move, leaving the tree unchanged.
     */
    cancel() {
      if (!this.active) {
        return;
      }
      announce('Move cancelled.', 'assertive');
      this.end();
    }

    /**
     * Idempotent teardown: drops the grabbed state and clears the highlight.
     */
    end() {
      this.grabbed?.classList.remove(CLASS_GRABBED);
      this.clearHighlight();
      this.indicator.clear();
      this.tree.closest('menu-tree')?.removeAttribute('data-mtu-drop');
      this.active = false;
      this.grabbed = null;
      this.positions = [];
      this.posIndex = -1;
      this.intent = null;
    }
  }

  Drupal.menuTreeUi.MoveModeController = MoveModeController;
})(Drupal);
