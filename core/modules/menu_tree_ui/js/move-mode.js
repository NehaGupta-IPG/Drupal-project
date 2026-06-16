/**
 * @file
 * Keyboard grab/select move mode for the menu tree.
 *
 * The keyboard counterpart to pointer drag. Space picks up the focused row; the
 * arrow keys move a drop position through the tree; Enter drops; Escape cancels.
 * It commits through the SAME commitMove() path as drag, so both produce
 * identical server moves and announcements.
 *
 * Instead of drawing a borrowed drop indicator, this carries the grabbed row
 * itself: as the cursor steps, the row (with its descendant subtree) is moved in
 * the DOM to the candidate slot and re-indented to the landing depth, so the
 * editor sees exactly where it will land. The carry is purely visual — on commit
 * the row is restored to its origin and the normal commitMove() runs against the
 * pre-move DOM; on cancel it is restored and left.
 *
 * Position model. Stops are precomputed in visual (pre-order) order:
 *  - "child" of a row   - drop as its FIRST child (one level deeper).
 *  - "before"/"after"   - drop as a sibling above the first row / below a row.
 * A row's "after" stop is emitted after its subtree, so Up/Down read top to
 * bottom. Right nests onto the current row, opening it if it is a closed parent
 * so its children become navigable; Left closes it. A pointer click on a row
 * while a row is grabbed sets the cursor to the clicked zone (reusing the drag
 * intent zones), so keyboard select and mouse positioning combine. The move also
 * survives a search: focus moving to the search field does not cancel it, and
 * jumping to a result re-aims the cursor at that row.
 *
 * Exposes Drupal.menuTreeUi.MoveModeController.
 */

((Drupal) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};
  const {
    commitMove,
    sameIntent,
    resolveNewParentId,
    resolveBeforeId,
    computeIntent,
  } = Drupal.menuTreeUi.drag;
  const { descendantRows, lastDescendantRow, nextSiblingRow, visibleRows } =
    Drupal.menuTreeUi.treeNav;

  const CLASS_GRABBED = 'menu-tree-ui-row--grabbed';

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
   * The 1-based level (depth) of a row.
   *
   * @param {HTMLElement} row
   *   The row.
   *
   * @return {number}
   *   The aria-level.
   */
  function levelOf(row) {
    return Number(row.getAttribute('aria-level'));
  }

  /**
   * Sets a row's level: aria-level plus the name cell's indent.
   *
   * @param {HTMLElement} row
   *   The row.
   * @param {number} level
   *   The 1-based level to apply.
   */
  function setRowLevel(row, level) {
    row.setAttribute('aria-level', String(level));
    const name = row.querySelector('.menu-tree-ui-row__name');
    if (name) {
      name.style.paddingInlineStart = `${(level - 1) * 1.25}rem`;
    }
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
      this.block = [];
      this.blockDelta = new Map();
      this.origLevel = 1;
      this.originParent = null;
      this.originNext = null;
      this.positions = [];
      this.posIndex = -1;
      this.intent = null;
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
      this.snapshotOrigin();
      this.buildPositions();
      if (this.positions.length === 0) {
        this.grabbed = null;
        announce('There is nowhere to move this item.', 'assertive');
        return false;
      }

      this.active = true;
      row.classList.add(CLASS_GRABBED);
      // Focus follows the grabbed row (not its handle) through the move, so the
      // <tr> is made focusable for the duration; end() restores it.
      row.tabIndex = 0;
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
     * Records the grabbed row's subtree and original slot, for carry + restore.
     */
    snapshotOrigin() {
      this.origLevel = levelOf(this.grabbed);
      this.block = descendantRows(this.grabbed);
      this.blockDelta = new Map(
        this.block.map((r) => [r, levelOf(r) - this.origLevel]),
      );
      const last = this.block.length
        ? this.block[this.block.length - 1]
        : this.grabbed;
      this.originParent = this.grabbed.parentNode;
      this.originNext = last.nextElementSibling;
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
     * Sets the cursor from a pointer click on a row, so mouse and keyboard
     * positioning combine.
     *
     * @param {MouseEvent} event
     *   The click event.
     *
     * @return {boolean}
     *   True if the click set a position (caller should preventDefault).
     */
    handlePointer(event) {
      if (!this.active || !(event.target instanceof Element)) {
        return false;
      }
      // Leave row controls (toggle, column switch, operations) clickable so the
      // editor can still expand a parent to aim inside it.
      if (
        event.target.closest(
          '.menu-tree-ui-row__toggle, .menu-tree-ui-row__column, .menu-tree-ui-row__operations',
        )
      ) {
        return false;
      }
      const row = event.target.closest('tr[role="row"]');
      if (!row || row === this.grabbed || this.block.includes(row)) {
        return false;
      }
      const intent = computeIntent(row, event.clientY);
      const i = this.indexOfStop(intent.kind, intent.target);
      if (i === -1) {
        return false;
      }
      this.setIntentByIndex(i);
      return true;
    }

    /**
     * Re-aims the cursor at a row reached via search jump-to, keeping the grab.
     *
     * @param {HTMLElement} row
     *   The jumped-to row.
     */
    aimAt(row) {
      if (!this.active || row === this.grabbed || this.block.includes(row)) {
        return;
      }
      this.buildPositions();
      let i = this.indexOfStop('before', row);
      if (i === -1) {
        i = this.indexOfStop('child', row);
      }
      if (i !== -1) {
        this.setIntentByIndex(i);
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
      const skip = new Set([this.grabbed, ...this.block]);
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
        const level = levelOf(row);
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
        this.setIntentByIndex(i);
      }
    }

    /**
     * Selects the position at an index: carries the row there and announces it.
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
      this.carryTo(intent);
      if (doAnnounce) {
        this.indicator.announce(intent);
      }
    }

    /**
     * Moves the grabbed row (and its subtree) to the candidate slot.
     *
     * The unit is detached first so the geometry helpers see a clean tree, then
     * re-indented to the landing depth and re-inserted: before the target, after
     * the target's whole subtree, or — for a "child" drop — immediately after
     * the target as its first child.
     *
     * @param {object} intent
     *   The DropIntent.
     */
    carryTo(intent) {
      const { target } = intent;
      const targetLevel = levelOf(target);
      // Detach first so the insertion reference is computed against a clean
      // tree: otherwise lastDescendantRow(target) could resolve to the carried
      // row itself (when it was previously dropped inside this target), and
      // re-inserting against a detached anchor would leave the row out of DOM.
      this.detachUnit();
      let depth;
      let ref;
      let after;
      if (intent.kind === 'child') {
        depth = targetLevel + 1;
        ref = target;
        after = true;
      } else if (intent.kind === 'before') {
        depth = targetLevel;
        ref = target;
        after = false;
      } else {
        depth = targetLevel;
        ref = lastDescendantRow(target) ?? target;
        after = true;
      }

      this.setUnitLevel(depth);
      this.insertUnit(ref, after);
      // Detaching the focused row dropped focus; put it back on the carried row
      // so the editor keeps control. The host's focusout handler defers its
      // cancel check and reads activeElement, which this restores.
      this.grabbed.focus();

      this.grabbed.scrollIntoView({ block: 'nearest' });
      this.tree
        .closest('menu-tree')
        ?.setAttribute(
          'data-mtu-drop',
          `${intent.kind}:${intent.target.dataset.id ?? ''}`,
        );
    }

    /**
     * The grabbed row plus its descendant block, in document order.
     *
     * @return {HTMLElement[]}
     *   The unit's rows.
     */
    unit() {
      return [this.grabbed, ...this.block];
    }

    /**
     * Detaches the grabbed unit from the DOM (references are retained).
     */
    detachUnit() {
      this.unit().forEach((row) => row.remove());
    }

    /**
     * Re-indents the grabbed unit so the grabbed row sits at the given depth.
     *
     * @param {number} depth
     *   The grabbed row's new level; block rows keep their relative offsets.
     */
    setUnitLevel(depth) {
      setRowLevel(this.grabbed, depth);
      this.block.forEach((row) => {
        setRowLevel(row, depth + this.blockDelta.get(row));
      });
    }

    /**
     * Inserts the grabbed unit before or after a reference row.
     *
     * @param {HTMLElement} ref
     *   The reference row.
     * @param {boolean} after
     *   True to insert after ref, false to insert before it.
     */
    insertUnit(ref, after) {
      const rows = this.unit();
      if (after) {
        let anchor = ref;
        rows.forEach((row) => {
          anchor.after(row);
          anchor = row;
        });
      } else {
        rows.forEach((row) => ref.parentNode.insertBefore(row, ref));
      }
    }

    /**
     * Restores the grabbed unit to its original slot and indentation.
     */
    restoreToOrigin() {
      this.detachUnit();
      this.setUnitLevel(this.origLevel);
      const rows = this.unit();
      if (this.originNext && this.originNext.parentNode === this.originParent) {
        rows.forEach((row) =>
          this.originParent.insertBefore(row, this.originNext),
        );
      } else {
        rows.forEach((row) => this.originParent.appendChild(row));
      }
      this.grabbed.focus();
    }

    /**
     * Commits the move, unless it resolves to the row's current slot (no-op).
     *
     * The carried row is restored to its origin first, so the no-op guard and
     * commitMove() resolve against the pre-move DOM exactly as a pointer drag
     * does; commitMove then re-fetches and lands the row at its destination.
     */
    commit() {
      if (!this.intent || !this.grabbed) {
        this.cancel();
        return;
      }
      const { grabbed, intent } = this;
      const movedId = grabbed.dataset.id;
      this.restoreToOrigin();

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
     * Cancels an in-progress move, restoring the row to where it started.
     */
    cancel() {
      if (!this.active) {
        return;
      }
      this.restoreToOrigin();
      announce('Move cancelled.', 'assertive');
      this.end();
    }

    /**
     * Idempotent teardown: drops the grabbed state and clears the indicator.
     *
     * Callers that end a carried move (commit, cancel) restore the row first.
     */
    end() {
      this.grabbed?.classList.remove(CLASS_GRABBED);
      // Drop the row out of the tab order without blurring it — removing the
      // attribute outright would blur the focused row to the body.
      if (this.grabbed) {
        this.grabbed.tabIndex = -1;
      }
      this.indicator.clear();
      this.tree.closest('menu-tree')?.removeAttribute('data-mtu-drop');
      this.active = false;
      this.grabbed = null;
      this.block = [];
      this.blockDelta = new Map();
      this.positions = [];
      this.posIndex = -1;
      this.intent = null;
    }
  }

  Drupal.menuTreeUi.MoveModeController = MoveModeController;
})(Drupal);
