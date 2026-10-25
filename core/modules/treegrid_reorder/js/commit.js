/**
 * @file
 * Translates a DropIntent into a server move + visible-state refresh.
 *
 * Server is the only source of truth. We POST to the move endpoint with no DOM
 * mutation up front; on success we re-fetch the affected levels (from-parent and
 * to-parent) and rebuild their rows in place. The whole class of "client moved
 * the DOM but forgot to update attribute X" bugs goes away — any attribute the
 * row needs is set by the renderer from the server's JSON.
 *
 * Trade-off: a moved row's previously-expanded descendants collapse after the
 * refresh. Acceptable for v1 — replay-expansion can be added later.
 *
 * The move response shape is {ok: boolean, code?, message?}.
 *
 * Per-row metadata that the children endpoint cannot express (e.g. whether a
 * parent now has children) is the consumer's concern: it is supplied as the
 * context's applyParentMetadata callback, and its onCommitted fires after a
 * successful move so the consumer can react (reveal an unsaved notice, etc.).
 *
 * The context a move runs with is { element, moveUrl(), applyParentMetadata,
 * onCommitted }, built by attach().
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
   * The inner commit pipeline (wrapped by commitMove for the in-flight flag).
   *
   * @param {HTMLTableRowElement} row
   *   The moved row.
   * @param {object} intent
   *   The DropIntent.
   * @param {object} context
   *   The move context: { element, moveUrl(), applyParentMetadata,
   *   onCommitted }.
   * @param {?HTMLTableElement} tree
   *   The root treegrid element.
   * @param {string} movedId
   *   The moved row's id.
   *
   * @return {Promise<void>}
   *   Resolves once the move is committed and refreshed (or bailed).
   */
  async function runCommit(row, intent, context, tree, movedId) {
    const { element } = context;
    const moveUrl = context.moveUrl();
    if (!moveUrl) {
      // eslint-disable-next-line no-console
      console.warn('[treegrid-reorder] no move URL — move ignored.');
      return;
    }
    const oldParentId = row.dataset.parentId ?? '';
    const newParentId = resolveNewParentId(intent);
    const { target } = intent;

    // A child drop opens an unloaded destination first, a leaf included: a
    // leaf's level comes back empty but loaded, which is enough to go ahead.
    if (
      intent.kind === 'child' &&
      target.getAttribute('aria-expanded') !== 'true'
    ) {
      const opened = await element.expandRow(target);
      if (!opened && target.dataset.treegridLoaded !== 'true') {
        announce(
          Drupal.t('Could not open the destination. Move canceled.'),
          'assertive',
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

    let body;
    try {
      const headers = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      };
      const token = await Drupal.treegrid.csrfToken(element);
      if (token !== '') {
        headers['X-CSRF-Token'] = token;
      }
      const response = await fetch(moveUrl, {
        method: 'POST',
        headers,
        credentials: 'same-origin',
        body: JSON.stringify({
          id: movedId,
          new_parent: newParentId,
          before_id: beforeId,
        }),
      });
      body = await response.json().catch(() => ({ ok: false }));
      if (!response.ok || !body.ok) {
        announce(body.message ?? Drupal.t('Move failed.'), 'assertive');
        return;
      }
    } catch (e) {
      // eslint-disable-next-line no-console
      console.error('[treegrid-reorder] move fetch failed', e);
      announce(Drupal.t('Network error. Move canceled.'), 'assertive');
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
    const okFrom = await element.refreshLevel(oldParentId);
    let okTo = true;
    if (newParentId !== oldParentId) {
      okTo = await element.refreshLevel(newParentId);
    }
    // A refresh whose fetch failed would leave the tree half-synced (a
    // duplicated row, or no tab stop). Reload so the tree resyncs from the
    // server rather than continuing from an inconsistent DOM.
    if (!okFrom || !okTo) {
      announce(Drupal.t('Could not refresh the tree. Reloading.'), 'assertive');
      window.location.reload();
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
    context.onCommitted?.();
  }

  /**
   * Commits a drag-and-drop move, flagging the host while in-flight.
   *
   * Tests wait on the data-treegrid-reorder-moving flag's absence to know all
   * asynchronous refresh work is done; humans never see it.
   *
   * @param {HTMLTableRowElement} row
   *   The moved row.
   * @param {object} intent
   *   The DropIntent.
   * @param {object} context
   *   The move context: { element, moveUrl(), applyParentMetadata,
   *   onCommitted }.
   *
   * @return {Promise<void>}
   *   Resolves once the commit completes.
   */
  async function commitMove(row, intent, context) {
    const movedId = row.dataset.id;
    if (!movedId) {
      return;
    }

    const { element } = context;
    element.setAttribute('data-treegrid-reorder-moving', 'true');
    try {
      await runCommit(row, intent, context, element.tree, movedId);
    } finally {
      element.removeAttribute('data-treegrid-reorder-moving');
    }
  }

  Drupal.treegridReorder.commitMove = commitMove;
  // Exported so keyboard move mode can resolve an intent to {new_parent,
  // before_id} for its no-op-drop guard, without duplicating the logic.
  Drupal.treegridReorder.resolveNewParentId = resolveNewParentId;
  Drupal.treegridReorder.resolveBeforeId = resolveBeforeId;
})(Drupal);
