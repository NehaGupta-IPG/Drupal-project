/**
 * @file
 * Keyboard grab/select move mode for a treegrid.
 *
 * The keyboard counterpart to pointer drag. Space (or a click on the drag
 * handle) picks up the focused row; the arrow keys move it; Enter drops;
 * Escape cancels. It commits through the SAME commitMove() path as drag, so
 * both produce identical server moves and announcements.
 *
 * Instead of drawing a borrowed drop indicator, this carries the grabbed row
 * itself: as the cursor moves, the row (with its descendant subtree) is moved
 * in the DOM to the candidate slot and re-indented to the landing depth, so the
 * editor sees exactly where it will land. The carry is purely visual — on
 * commit the row is restored to its origin and the normal commitMove() runs
 * against the pre-move DOM; on cancel it is restored and left.
 *
 * Position model. The cursor is a (gap, depth) pair over the visible rows
 * (excluding the carried row and its subtree):
 *  - gap is a slot between two consecutive visible rows. Up/Down move it one
 *    visual position, so the carried row travels straight up and down the list.
 *  - depth is the level the row would land at in that gap. Left/Right change it,
 *    clamped to what the neighbors allow: at most one level deeper than the row
 *    above, and no shallower than the row below. Right onto a closed parent
 *    opens it so the row can nest among its children; the held row stays visible
 *    throughout — no gesture closes the section it sits in.
 *
 * A (gap, depth) pair resolves to a DropIntent {kind, target} so the carry and
 * commit reuse the drag code. A pointer click on a row while a row is grabbed
 * sets the cursor to the clicked zone (reusing the drag intent zones), so
 * keyboard select and mouse positioning combine. The move also survives a
 * search: focus moving to the search field does not cancel it, and jumping to a
 * result re-aims the cursor at that row.
 *
 * The picked-up row carries data-treegrid-reorder-grabbed, and the live drop
 * position is recorded on the element as data-treegrid-reorder-drop.
 *
 * Exposes Drupal.treegridReorder.MoveModeController.
 */

((Drupal) => {
  Drupal.treegridReorder = Drupal.treegridReorder || {};
  const {
    commitMove,
    sameIntent,
    resolveNewParentId,
    resolveBeforeId,
    computeIntent,
    rowTitle,
  } = Drupal.treegridReorder;
  const {
    descendantRows,
    lastDescendantRow,
    nextSiblingRow,
    visibleRows,
    findRow,
    levelOf,
    setRowLevel,
  } = Drupal.treegrid.treeNav;
  const { announce } = Drupal.treegrid;

  const GRABBED = 'data-treegrid-reorder-grabbed';
  // Elements that keep working while a row is picked up, so the editor can open
  // a parent or flip a control. Anything else in a row, a link included, aims
  // the move.
  const LIVE_DURING_MOVE =
    '[data-treegrid-toggle], button, input, select, textarea';

  /**
   * Clamps a number to an inclusive range.
   *
   * @param {number} value
   *   The value.
   * @param {number} min
   *   The lower bound.
   * @param {number} max
   *   The upper bound.
   *
   * @return {number}
   *   The clamped value.
   */
  function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
  }

  /**
   * The translated "picked up" announcement for a prospective drop.
   *
   * Built as three full sentences (one per drop kind) rather than concatenating
   * translated fragments, so each is translatable on its own.
   *
   * @param {string} item
   *   The title of the picked-up item.
   * @param {object} intent
   *   The DropIntent.
   *
   * @return {string}
   *   The translated placement sentence.
   */
  function placementMessage(item, intent) {
    const target = rowTitle(intent.target);
    if (intent.kind === 'child') {
      return Drupal.t('Picked up @item. It will drop as a child of @target.', {
        '@item': item,
        '@target': target,
      });
    }
    if (intent.kind === 'before') {
      return Drupal.t('Picked up @item. It will drop before @target.', {
        '@item': item,
        '@target': target,
      });
    }
    return Drupal.t('Picked up @item. It will drop after @target.', {
      '@item': item,
      '@target': target,
    });
  }

  /**
   * Reads the id of a row's following sibling row, or null.
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
     * @param {HTMLElement} element
     *   The <drupal-treegrid> element, for opening rows.
     * @param {Drupal.treegridReorder.DropIndicator} indicator
     *   The shared drop indicator (its announce() is reused for speech).
     * @param {object} context
     *   The move context passed to commitMove: { element, moveUrl(),
     *   applyParentMetadata }.
     */
    constructor(element, indicator, context) {
      this.element = element;
      this.indicator = indicator;
      this.context = context;

      this.active = false;
      this.grabbed = null;
      this.block = [];
      this.blockDelta = new Map();
      this.origLevel = 1;
      this.originPreviousId = null;
      this.originParentId = '';
      this.originNext = null;
      // Whether rows are about to be added to the tree.
      this.insertPending = false;
      this.onBeforeRowsChange = this.onBeforeRowsChange.bind(this);
      this.onLevelLoaded = this.onLevelLoaded.bind(this);
      // The cursor: a slot index between visible rows, and the depth the row
      // would land at there.
      this.gap = 0;
      this.depth = 1;
      this.intent = null;
      // Bumped on every cursor move. openInto() captures it before its async
      // expand and bails on return if the cursor has moved since, so a slow
      // open never yanks the row back from where later keys put it.
      this.cursorSeq = 0;
    }

    /**
     * @return {HTMLTableElement}
     *   The element's treegrid table.
     */
    get tree() {
      return this.element.tree;
    }

    /**
     * @return {boolean}
     *   Whether a row is currently picked up.
     */
    isActive() {
      return this.active;
    }

    /**
     * @param {?HTMLElement} row
     *   The row to test, or null.
     *
     * @return {boolean}
     *   Whether the row is the picked-up row or part of its carried subtree.
     */
    isGrabbed(row) {
      return !!row && (row === this.grabbed || this.block.includes(row));
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
      if (this.visibleList().length === 0) {
        this.grabbed = null;
        announce(Drupal.t('There is nowhere to move this item.'), 'assertive');
        return false;
      }

      this.active = true;
      this.element.addEventListener(
        'treegrid:before-rows-change',
        this.onBeforeRowsChange,
      );
      this.element.addEventListener(
        'treegrid:level-loaded',
        this.onLevelLoaded,
      );
      row.setAttribute(GRABBED, 'true');
      // Tell the treegrid to keep this row visible: its collapse guard refuses
      // to fold a section containing a data-treegrid-keep-visible row, so the
      // carried row never vanishes mid-move.
      row.dataset.treegridKeepVisible = 'true';
      // The grabbed row is the selection (aria-selected) and the single tab stop
      // while it is held; the cursor (focus) follows it through the carry.
      const prevStop = this.tree.querySelector('tr[role="row"][tabindex="0"]');
      if (prevStop && prevStop !== row) {
        prevStop.setAttribute('tabindex', '-1');
      }
      row.tabIndex = 0;
      row.setAttribute('aria-selected', 'true');
      this.setInitialCursor();

      const instructions = Drupal.t(
        'Use the Up and Down arrows to move it, Left and Right to change its indent, Enter to drop, or Escape to cancel.',
      );
      announce(
        `${placementMessage(rowTitle(row), this.intent)} ${instructions}`,
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
      // The slot the cursor starts in; kept as a row only for that.
      this.originNext = last.nextElementSibling;
      // Where the row goes back to: right after the previous item at its level
      // and block, or right after its parent when it was the first child, or
      // the start of the tree body when it was the first top-level row. Rows
      // landing elsewhere during the carry don't move that spot.
      this.originPreviousId =
        Drupal.treegridReorder.previousSiblingRow(this.grabbed)?.dataset.id ??
        null;
      this.originParentId = this.grabbed.dataset.parentId ?? '';
    }

    /**
     * Handles a keydown while move mode is active.
     *
     * @param {KeyboardEvent} event
     *   The keydown event.
     *
     * @return {boolean}
     *   True if the key was consumed (caller should preventDefault). Tab and
     *   any other key return false so focus can leave; the move survives that
     *   and is ended only by Escape, a drop, or a pointer press outside the UI.
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
          this.deepen();
          return true;
        case 'ArrowLeft':
          this.shallow();
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
      // Leave the toggle and row controls clickable so the editor can still
      // expand a parent to aim inside it.
      if (event.target.closest(LIVE_DURING_MOVE)) {
        return false;
      }
      const row = event.target.closest('tr[role="row"]');
      if (!row || row === this.grabbed || this.block.includes(row)) {
        return false;
      }
      const list = this.visibleList();
      const placed = this.gapDepthForIntent(
        computeIntent(row, event.clientY),
        list,
      );
      if (!placed) {
        return false;
      }
      this.setCursor(placed.gap, placed.depth, list);
      return true;
    }

    /**
     * Re-aims the cursor at a row reached via search jump-to, keeping the grab.
     *
     * @param {HTMLElement} row
     *   The jumped-to row.
     *
     * @return {boolean}
     *   False when no move is active or the row can't be aimed at.
     */
    aimAt(row) {
      if (!this.active || row === this.grabbed || this.block.includes(row)) {
        return false;
      }
      const list = this.visibleList();
      const gap = list.indexOf(row);
      if (gap === -1) {
        return false;
      }
      const { min, max } = this.gapBounds(gap, list);
      this.setCursor(gap, clamp(this.depth, min, max), list);
      return true;
    }

    /**
     * The visible rows the cursor moves over: every visible row except the
     * carried row and its subtree.
     *
     * @return {HTMLElement[]}
     *   The candidate rows, in document order.
     */
    visibleList() {
      const skip = new Set([this.grabbed, ...this.block]);
      return visibleRows(this.tree).filter((row) => !skip.has(row));
    }

    /**
     * The depth range allowed at a gap.
     *
     * The carried row can be at most one level deeper than the row above the
     * gap, and no shallower than the row below it (going shallower there would
     * orphan that row). With no row above, only the root level is allowed.
     *
     * @param {number} gap
     *   The gap index (0..list.length).
     * @param {HTMLElement[]} list
     *   The visible candidate rows.
     *
     * @return {{prev: ?HTMLElement, min: number, max: number}}
     *   The row above the gap and the inclusive depth bounds.
     */
    gapBounds(gap, list) {
      const prev = list[gap - 1] ?? null;
      const next = list[gap] ?? null;
      const max = prev ? levelOf(prev) + 1 : 1;
      const min = Math.min(next ? levelOf(next) : 1, max);
      return { prev, min, max };
    }

    /**
     * Resolves a (gap, depth) cursor to a DropIntent.
     *
     * Above the first row it is a "before" drop. Otherwise the row lands after
     * the row above (prev): one level deeper makes it prev's first child;
     * otherwise it is a sibling placed after the subtree of prev's ancestor at
     * the chosen depth.
     *
     * @param {number} gap
     *   The gap index.
     * @param {number} depth
     *   The chosen depth.
     * @param {HTMLElement[]} list
     *   The visible candidate rows.
     *
     * @return {?object}
     *   The DropIntent, or null when there is nowhere to drop.
     */
    resolveIntent(gap, depth, list) {
      const prev = list[gap - 1] ?? null;
      if (!prev) {
        return list[0] ? { kind: 'before', target: list[0] } : null;
      }
      if (depth >= levelOf(prev) + 1) {
        return { kind: 'child', target: prev };
      }
      let ancestor = prev;
      while (ancestor && levelOf(ancestor) > depth) {
        ancestor = findRow(this.tree, ancestor.dataset.parentId);
      }
      return { kind: 'after', target: ancestor ?? prev };
    }

    /**
     * Maps a pointer-zone DropIntent to the (gap, depth) the cursor uses.
     *
     * @param {object} intent
     *   A {kind, target} intent from computeIntent().
     * @param {HTMLElement[]} list
     *   The visible candidate rows.
     *
     * @return {?{gap: number, depth: number}}
     *   The cursor, or null when the target isn't a candidate row.
     */
    gapDepthForIntent(intent, list) {
      const i = list.indexOf(intent.target);
      if (i === -1) {
        return null;
      }
      const level = levelOf(intent.target);
      if (intent.kind === 'child') {
        return { gap: i + 1, depth: level + 1 };
      }
      if (intent.kind === 'before') {
        return { gap: i, depth: level };
      }
      // 'after' lands past the target's whole visible subtree.
      let j = i + 1;
      while (j < list.length && levelOf(list[j]) > level) {
        j += 1;
      }
      return { gap: j, depth: level };
    }

    /**
     * Sets the initial cursor to the grabbed row's current slot, so an
     * immediate Enter is a no-op and the arrows start from where the row is.
     */
    setInitialCursor() {
      const list = this.visibleList();
      const gap =
        this.originNext && list.includes(this.originNext)
          ? list.indexOf(this.originNext)
          : list.length;
      const { min, max } = this.gapBounds(gap, list);
      this.setCursor(gap, clamp(this.origLevel, min, max), list, false);
    }

    /**
     * Moves the cursor up (-1) or down (+1) one visual position, keeping the
     * depth where the new gap allows it.
     *
     * @param {number} direction
     *   -1 for up, +1 for down.
     */
    step(direction) {
      const list = this.visibleList();
      const gap = clamp(this.gap + direction, 0, list.length);
      if (gap === this.gap) {
        return;
      }
      const { min, max } = this.gapBounds(gap, list);
      this.setCursor(gap, clamp(this.depth, min, max), list);
    }

    /**
     * Right-arrow: nest one level deeper, opening a closed parent so the row
     * can land among its children.
     */
    deepen() {
      const list = this.visibleList();
      const { prev, max } = this.gapBounds(this.gap, list);
      if (this.depth >= max) {
        return;
      }
      const depth = this.depth + 1;
      this.setCursor(this.gap, depth, list);
      if (
        prev &&
        depth === levelOf(prev) + 1 &&
        prev.dataset.hasChildren === 'true' &&
        prev.getAttribute('aria-expanded') !== 'true'
      ) {
        this.openInto(prev);
      }
    }

    /**
     * Left-arrow: pull the row out one level, down to what the gap allows.
     *
     * Never collapses anything — the carried row stays visible (see the file
     * docblock); going shallower simply re-targets the drop to a higher
     * ancestor.
     */
    shallow() {
      const list = this.visibleList();
      const { min } = this.gapBounds(this.gap, list);
      if (this.depth <= min) {
        return;
      }
      this.setCursor(this.gap, this.depth - 1, list);
    }

    /**
     * Opens a collapsed parent the cursor just nested into, then keeps the
     * carried row as its first child.
     *
     * @param {HTMLElement} prev
     *   The parent row to open.
     *
     * @return {Promise<void>}
     *   Resolves once the children are in DOM and the cursor is re-placed.
     */
    async openInto(prev) {
      const seq = this.cursorSeq;
      const opened = await this.element.expandRow(prev);
      // The move may have been committed or canceled during the fetch, or the
      // editor may have moved the cursor on - in which case the open is done
      // (the section is now navigable) but the cursor must be left where it is.
      if (!this.active || !this.grabbed || this.cursorSeq !== seq) {
        return;
      }
      // A failed or canceled load leaves the row closed; announce that instead
      // of a success that didn't happen, and leave the cursor where it is
      // rather than re-placing it as if it opened.
      if (!opened) {
        announce(
          Drupal.t('Could not open @title.', {
            '@title': rowTitle(prev),
          }),
          'assertive',
        );
        return;
      }
      announce(
        Drupal.t('Opened @title.', {
          '@title': rowTitle(prev),
        }),
      );
      const list = this.visibleList();
      const gap = list.indexOf(prev) + 1;
      const { min, max } = this.gapBounds(gap, list);
      this.setCursor(gap, clamp(levelOf(prev) + 1, min, max), list);
    }

    /**
     * Selects a (gap, depth) cursor: carries the row there and announces it.
     *
     * @param {number} gap
     *   The gap index.
     * @param {number} depth
     *   The chosen depth.
     * @param {HTMLElement[]} list
     *   The visible candidate rows the cursor was computed against.
     * @param {boolean} [doAnnounce]
     *   Whether to announce the position (the grab path passes false and
     *   announces a fuller message itself).
     */
    setCursor(gap, depth, list, doAnnounce = true) {
      const intent = this.resolveIntent(gap, depth, list);
      if (!intent) {
        return;
      }
      this.gap = gap;
      this.depth = depth;
      this.cursorSeq += 1;
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
      // The carried row must always be visible, even when it lands inside a
      // collapsed run (see request: "I should always be able to see it").
      delete this.grabbed.dataset.treegridHidden;
      // Detaching the focused row dropped focus; put it back on the carried row
      // so the keyboard keeps driving the move.
      this.grabbed.focus();

      this.grabbed.scrollIntoView({ block: 'nearest' });
      this.tree
        .closest('drupal-treegrid')
        ?.setAttribute(
          'data-treegrid-reorder-drop',
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
      const previous = this.originPreviousId
        ? findRow(this.tree, this.originPreviousId)
        : null;
      const parent = findRow(this.tree, this.originParentId);
      if (previous) {
        this.insertUnit(lastDescendantRow(previous) ?? previous, true);
      } else if (parent) {
        this.insertUnit(parent, true);
      } else {
        const body = this.tree.querySelector('tbody');
        body.prepend(...this.unit());
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
      // A drop refused while another change is in the way leaves the carry
      // where it is, so the editor can drop again or press Escape.
      if (Drupal.treegridReorder.isBusy(this.element)) {
        Drupal.treegridReorder.refuseMove(this.element, {
          id: movedId,
          parentId: resolveNewParentId(intent),
          beforeId: resolveBeforeId(intent),
          gesture: 'move-mode',
        });
        return;
      }
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
        announce(
          Drupal.t('Move canceled. The item is already there.'),
          'assertive',
        );
        this.end();
        return;
      }

      // Tear down before the async commit (commitMove re-finds and focuses the
      // landed row, and announces success or failure itself).
      this.end();
      commitMove(grabbed, intent, this.context, 'move-mode');
    }

    /**
     * Gives up the carry when the rows it moves among are about to change.
     *
     * A level that is reconciled may remove, reorder or rebuild rows, so the
     * carried row goes back where it started and the move ends. Rows that are
     * only being added leave the carry alone; the cursor is recomputed once
     * they are in.
     *
     * @param {CustomEvent} event
     *   The treegrid:before-rows-change event.
     */
    onBeforeRowsChange(event) {
      if (!this.active) {
        return;
      }
      if (event.detail.change === 'reconcile') {
        this.restoreToOrigin();
        announce(Drupal.t('Move canceled. The tree changed.'), 'assertive');
        this.end();
        return;
      }
      this.insertPending = true;
    }

    /**
     * Re-places the carried row against its target once added rows are in.
     */
    onLevelLoaded() {
      if (!this.active || !this.insertPending || !this.intent) {
        return;
      }
      this.insertPending = false;
      const list = this.visibleList();
      const placed = this.gapDepthForIntent(this.intent, list);
      if (placed) {
        this.gap = placed.gap;
        this.depth = placed.depth;
      }
      this.carryTo(this.intent);
    }

    /**
     * Cancels an in-progress move, restoring the row to where it started.
     */
    cancel() {
      if (!this.active) {
        return;
      }
      this.restoreToOrigin();
      announce(Drupal.t('Move canceled.'), 'assertive');
      this.end();
    }

    /**
     * Idempotent teardown: drops the grabbed state and clears the indicator.
     *
     * Callers that end a carried move (commit, cancel) restore the row first.
     */
    end() {
      this.element.removeEventListener(
        'treegrid:before-rows-change',
        this.onBeforeRowsChange,
      );
      this.element.removeEventListener(
        'treegrid:level-loaded',
        this.onLevelLoaded,
      );
      this.insertPending = false;
      this.grabbed?.removeAttribute(GRABBED);
      delete this.grabbed?.dataset.treegridKeepVisible;
      // The dropped (or restored) row stays the cursor and the single tab stop,
      // so leave its tabindex at 0; just clear the picked-up selection marker.
      // After a commit, commit.js re-focuses the rebuilt row via focusRow.
      this.grabbed?.removeAttribute('aria-selected');
      this.indicator.clear();
      this.tree
        .closest('drupal-treegrid')
        ?.removeAttribute('data-treegrid-reorder-drop');
      this.active = false;
      this.grabbed = null;
      this.block = [];
      this.blockDelta = new Map();
      this.gap = 0;
      this.depth = 1;
      this.intent = null;
    }
  }

  Drupal.treegridReorder.MoveModeController = MoveModeController;
})(Drupal);
