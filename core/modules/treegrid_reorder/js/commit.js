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
 * The move response shape is {ok: boolean, weight?, code?, message?}.
 *
 * Per-row metadata that the children endpoint cannot express (e.g. whether a
 * parent now has children) is the consumer's concern: it is supplied as the
 * options.applyParentMetadata callback, and options.onCommitted fires after a
 * successful move so the consumer can react (reveal an unsaved notice, etc.).
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
   * @param {string} moveUrl
   *   The move endpoint.
   * @param {object} expand
   *   The treegrid ExpandManager, for destination auto-expand + group
   *   refreshes.
   * @param {?HTMLTableElement} tree
   *   The root treegrid element.
   * @param {string} movedId
   *   The moved row's id.
   * @param {string} csrfToken
   *   The X-CSRF-Token value, or ''.
   * @param {object} options
   *   { applyParentMetadata, onCommitted } consumer callbacks.
   *
   * @return {Promise<void>}
   *   Resolves once the move is committed and refreshed (or bailed).
   */
  async function runCommit(
    row,
    intent,
    moveUrl,
    expand,
    tree,
    movedId,
    csrfToken,
    options,
  ) {
    const oldParentId = row.dataset.parentId ?? '';
    const newParentId = resolveNewParentId(intent);
    const { target } = intent;

    if (
      intent.kind === 'child' &&
      target.dataset.hasChildren === 'true' &&
      target.getAttribute('aria-expanded') !== 'true'
    ) {
      await expand.expand(target);
      if (target.getAttribute('aria-expanded') !== 'true') {
        announce(
          Drupal.t('Could not open destination — move cancelled.'),
          'assertive',
        );
        return;
      }
    }

    // Resolve before_id AFTER any auto-expand: for a 'child' drop the first
    // child is only in the DOM once the parent is open, and first-child
    // placement depends on finding it.
    const beforeId = resolveBeforeId(intent);

    let body;
    try {
      const response = await fetch(moveUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-Token': csrfToken ?? '',
        },
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
      announce(Drupal.t('Network error — move cancelled.'), 'assertive');
      return;
    }

    // Server confirmed. Re-fetch the visible state of both affected groups.
    // When from === to (sibling reorder under the same parent) the second
    // refresh is a no-op but harmless.
    await expand.refresh(oldParentId);
    if (newParentId !== oldParentId) {
      await expand.refresh(newParentId);
    }

    options.applyParentMetadata?.(oldParentId);
    options.applyParentMetadata?.(newParentId);

    // row and intent.target are detached after refresh — re-find by id.
    if (tree) {
      const fresh = findRow(tree, movedId);
      if (fresh) {
        focusRow(tree, fresh);
      }
    }

    announce(Drupal.t('Item moved.'), 'polite');
    options.onCommitted?.();
  }

  /**
   * Commits a drag-and-drop move, flagging the host while in-flight.
   *
   * Tests wait on the data-mtu-moving flag's absence to know all asynchronous
   * refresh work is done; humans never see it.
   *
   * @param {HTMLTableRowElement} row
   *   The moved row.
   * @param {object} intent
   *   The DropIntent.
   * @param {string} moveUrl
   *   The move endpoint.
   * @param {object} expand
   *   The treegrid ExpandManager.
   * @param {string} csrfToken
   *   The X-CSRF-Token value, or ''.
   * @param {object} options
   *   { applyParentMetadata, onCommitted } consumer callbacks.
   *
   * @return {Promise<void>}
   *   Resolves once the commit completes.
   */
  async function commitMove(row, intent, moveUrl, expand, csrfToken, options) {
    const movedId = row.dataset.id;
    if (!movedId) {
      return;
    }

    const tree = row.closest('table[role="treegrid"]');
    const host = tree?.closest('drupal-treegrid') ?? null;
    host?.setAttribute('data-mtu-moving', 'true');
    try {
      await runCommit(
        row,
        intent,
        moveUrl,
        expand,
        tree,
        movedId,
        csrfToken,
        options,
      );
    } finally {
      host?.removeAttribute('data-mtu-moving');
    }
  }

  Drupal.treegridReorder.commitMove = commitMove;
  // Exported so keyboard move mode can resolve an intent to {new_parent,
  // before_id} for its no-op-drop guard, without duplicating the logic.
  Drupal.treegridReorder.resolveNewParentId = resolveNewParentId;
  Drupal.treegridReorder.resolveBeforeId = resolveBeforeId;
})(Drupal);
