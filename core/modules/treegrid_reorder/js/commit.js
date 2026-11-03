/**
 * @file
 * Translates a DropIntent into a server move + visible-state refresh.
 *
 * Server is the only source of truth. We POST to the move endpoint with no DOM
 * mutation up front; on success we re-fetch the affected levels (from-parent and
 * to-parent) and reconcile their rows in place. The whole class of "client moved
 * the DOM but forgot to update attribute X" bugs goes away — any attribute the
 * row needs comes from the rows the server renders.
 *
 * Trade-off: a moved row's previously-expanded descendants collapse after the
 * refresh. Acceptable for v1 — replay-expansion can be added later.
 *
 * The move response shape is {ok: boolean, code?, message?}.
 *
 * Per-row metadata that the children endpoint cannot express (e.g. whether a
 * parent now has children) is the consumer's concern: it is supplied as the
 * context's applyParentMetadata callback.
 *
 * A move runs as one unit in the element's queue and sends through the request
 * helper. A recorded move fires treegrid-reorder:moved on the element, a move
 * that was not recorded fires treegrid-reorder:failed.
 *
 * The context a move runs with is { element, moveUrl(), applyParentMetadata },
 * built by attach().
 *
 * Exposes Drupal.treegridReorder.commitMove.
 */

((Drupal) => {
  Drupal.treegridReorder = Drupal.treegridReorder || {};
  const { childRows, findRow, focusRow, nextSiblingRow } =
    Drupal.treegrid.treeNav;
  const { announce } = Drupal.treegrid;

  /**
   * Resolves the parent id the moved row should end up under.
   *
   * @param {object} intent
   *   The DropIntent.
   *
   * @return {string}
   *   The destination parent id, or ''.
   */
  function resolveNewParentId(intent) {
    if (intent.kind === 'child') {
      return intent.target.dataset.id ?? '';
    }
    return intent.target.dataset.parentId ?? '';
  }

  /**
   * Resolves the before_id payload for the move.
   *
   * 'before' → the target; 'after' → the target's next sibling row (or null
   * for end-of-list); 'child' → the target's first existing child, so the
   * moved row lands as the FIRST child (right under the parent header, where
   * the drop is aimed). A collapsed parent whose children aren't in the DOM,
   * and a leaf, fall through to null (the only / appended child), which the
   * server and commit-time auto-expand handle.
   *
   * @param {object} intent
   *   The DropIntent.
   *
   * @return {?string}
   *   The before_id, or null.
   */
  function resolveBeforeId(intent) {
    if (intent.kind === 'child') {
      return childRows(intent.target)[0]?.dataset.id ?? null;
    }
    if (intent.kind === 'before') {
      return intent.target.dataset.id ?? null;
    }
    return nextSiblingRow(intent.target)?.dataset.id ?? null;
  }

  /**
   * Dispatches a move event on the element.
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element.
   * @param {string} type
   *   The event type, treegrid-reorder:moved or treegrid-reorder:failed.
   * @param {object} detail
   *   The event detail.
   */
  function dispatchMoveEvent(element, type, detail) {
    element.dispatchEvent(new CustomEvent(type, { bubbles: true, detail }));
  }

  /**
   * The message that tells an editor a move was not recorded.
   *
   * @param {object} result
   *   The request helper's result.
   * @param {string} failed
   *   The message for a refused move with no message of its own.
   * @param {string} network
   *   The message for a request that never got an answer.
   *
   * @return {string}
   *   The server's message, or the matching fallback.
   */
  function failureMessage(result, failed, network) {
    if (result.message) {
      return result.message;
    }
    return result.sent && result.status === 0 ? network : failed;
  }

  /**
   * Reports a move that was not recorded: announces it and fires failed.
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element.
   * @param {object} move
   *   What was attempted: { id, parentId, beforeId, gesture }.
   * @param {object} result
   *   The request helper's result, or one built for a move never sent.
   * @param {string} message
   *   What to announce.
   */
  function reportFailure(element, move, result, message) {
    announce(message, 'assertive');
    dispatchMoveEvent(element, 'treegrid-reorder:failed', {
      ...move,
      status: result.status,
      code: result.code,
      message: result.message,
      sent: result.sent,
    });
  }

  /**
   * Reports a gesture refused because another change is in the way.
   *
   * Announces it once per busy period and fires failed with the code refused.
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element.
   * @param {object} move
   *   What was attempted: { id, parentId, beforeId, gesture }.
   */
  function refuseMove(element, move) {
    Drupal.treegridReorder.announceRefusal(element);
    dispatchMoveEvent(element, 'treegrid-reorder:failed', {
      ...move,
      status: 0,
      code: 'refused',
      message: null,
      sent: false,
    });
  }

  /**
   * The result of a move that never left the browser.
   *
   * @param {string} code
   *   The module's code for why.
   *
   * @return {object}
   *   A result shaped like the request helper's.
   */
  function unsentResult(code) {
    return {
      ok: false,
      status: 0,
      code,
      message: null,
      body: null,
      sent: false,
    };
  }

  /**
   * Runs a drag or move-mode move inside its unit.
   *
   * @param {object} unit
   *   The unit the move runs in.
   * @param {HTMLTableRowElement} row
   *   The moved row.
   * @param {object} intent
   *   The DropIntent.
   * @param {object} context
   *   The move context: { element, moveUrl(), applyParentMetadata }.
   * @param {string} gesture
   *   'drag' or 'move-mode'.
   *
   * @return {Promise<void>}
   *   Resolves once the move is committed and refreshed (or bailed).
   */
  async function runMove(unit, row, intent, context, gesture) {
    const { element } = context;
    const { tree } = element;
    const movedId = row.dataset.id;
    const moveUrl = context.moveUrl();
    if (!moveUrl) {
      console.warn('[treegrid-reorder] no move URL — move ignored.');
      return;
    }
    const oldParentId = row.dataset.parentId ?? '';
    const newParentId = resolveNewParentId(intent);
    const { target } = intent;
    const move = {
      id: movedId,
      parentId: newParentId,
      beforeId: null,
      gesture,
    };

    // The moved row or the target can be gone by the time the unit starts, for
    // instance after a refresh that ran ahead of it.
    if (!row.isConnected || !target.isConnected) {
      reportFailure(
        element,
        move,
        unsentResult('unavailable'),
        Drupal.t('That item is no longer there. Move canceled.'),
      );
      return;
    }

    // A child drop opens an unloaded destination first, a leaf included: a
    // leaf's level comes back empty but loaded, which is enough to go ahead.
    if (
      intent.kind === 'child' &&
      target.getAttribute('aria-expanded') !== 'true'
    ) {
      const opened = await unit.expandRow(target);
      if (!opened && target.dataset.treegridLoaded !== 'true') {
        reportFailure(
          element,
          move,
          unsentResult('unavailable'),
          Drupal.t('Could not open the destination. Move canceled.'),
        );
        return;
      }
    }

    // Resolve before_id AFTER any auto-expand: for a 'child' drop the first
    // child is only in the DOM once the parent is open, and first-child
    // placement depends on finding it.
    let beforeId = resolveBeforeId(intent);
    // A drop released just below the row above the dragged row resolves to
    // the dragged row's own id, i.e. "before yourself", which the server
    // treats as end-of-list. Remap to the current next sibling, as move mode
    // already does for its own no-op guard.
    if (beforeId === movedId) {
      beforeId = nextSiblingRow(row)?.dataset.id ?? null;
    }
    move.beforeId = beforeId;

    const result = await Drupal.treegridReorder.request(element, moveUrl, {
      body: { id: movedId, new_parent: newParentId, before_id: beforeId },
      unit,
    });
    if (!result.ok) {
      reportFailure(
        element,
        move,
        result,
        failureMessage(
          result,
          Drupal.t('Move failed.'),
          Drupal.t('Network error. Move canceled.'),
        ),
      );
      return;
    }

    // The destination of a child drop is open from here on, so the moved row is
    // inserted visible under it (a leaf is not marked open by its empty load).
    if (intent.kind === 'child') {
      target.setAttribute('aria-expanded', 'true');
    }

    // Server confirmed. Re-fetch the visible state of both affected groups.
    // When from === to (sibling reorder under the same parent) the second
    // refresh is a no-op but harmless.
    const okFrom = await unit.refreshLevel(oldParentId);
    let okTo = true;
    if (newParentId !== oldParentId) {
      okTo = await unit.refreshLevel(newParentId);
    }
    // A refresh whose fetch failed leaves the tree out of step with a move
    // that was recorded: the move is not a failure, but the tree no longer
    // shows the truth, so further moves are refused until it is reloaded.
    if (!okFrom || !okTo) {
      announce(
        Drupal.t(
          'The move was saved, but the tree could not be redrawn. Reload the page to see it.',
        ),
        'assertive',
      );
      dispatchMoveEvent(element, 'treegrid-reorder:moved', {
        id: movedId,
        parentId: newParentId,
        beforeId,
        previousParentId: oldParentId,
        gesture,
        refreshed: false,
        result,
      });
      Drupal.treegridReorder.enterNeedsReload(element, 'refresh-failed');
      return;
    }

    context.applyParentMetadata?.(oldParentId);
    context.applyParentMetadata?.(newParentId);

    // row and intent.target are detached after refresh — re-find by id. Only
    // pull focus back to the tree if it is still there (or on body because the
    // refresh detached the focused row); a user who moved to the search field
    // during the async round-trip keeps their place.
    if (tree) {
      const fresh = findRow(tree, movedId);
      const active = document.activeElement;
      if (fresh && (active === document.body || tree.contains(active))) {
        focusRow(tree, fresh);
      }
    }

    announce(Drupal.t('Item moved.'), 'polite');
    dispatchMoveEvent(element, 'treegrid-reorder:moved', {
      id: movedId,
      parentId: newParentId,
      beforeId,
      previousParentId: oldParentId,
      gesture,
      refreshed: true,
      result,
    });
  }

  /**
   * Commits a drag or move-mode move as one unit in the element's queue.
   *
   * The host carries data-treegrid-reorder-moving from here until the unit
   * ends, the time spent queued included. The attribute is present while a
   * move is queued, being sent or being followed by its level refreshes, and
   * absent once all of that work is done.
   *
   * @param {HTMLTableRowElement} row
   *   The moved row.
   * @param {object} intent
   *   The DropIntent.
   * @param {object} context
   *   The move context: { element, moveUrl(), applyParentMetadata }.
   * @param {string} gesture
   *   'drag' or 'move-mode'.
   *
   * @return {Promise<void>}
   *   Resolves once the commit completes.
   */
  async function commitMove(row, intent, context, gesture) {
    if (!row.dataset.id) {
      return;
    }
    const { element } = context;
    if (!Drupal.treegridReorder.acceptMove(element)) {
      refuseMove(element, {
        id: row.dataset.id,
        parentId: resolveNewParentId(intent),
        beforeId: resolveBeforeId(intent),
        gesture,
      });
      return;
    }
    try {
      await element.exclusive((unit) =>
        runMove(unit, row, intent, context, gesture),
      );
    } finally {
      Drupal.treegridReorder.finishMove(element);
    }
  }

  Drupal.treegridReorder.commitMove = commitMove;
  Drupal.treegridReorder.dispatchMoveEvent = dispatchMoveEvent;
  Drupal.treegridReorder.failureMessage = failureMessage;
  Drupal.treegridReorder.reportFailure = reportFailure;
  Drupal.treegridReorder.refuseMove = refuseMove;
  Drupal.treegridReorder.unsentResult = unsentResult;
  // Exported so keyboard move mode can resolve an intent to {new_parent,
  // before_id} for its no-op-drop guard, without duplicating the logic.
  Drupal.treegridReorder.resolveNewParentId = resolveNewParentId;
  Drupal.treegridReorder.resolveBeforeId = resolveBeforeId;
})(Drupal);
