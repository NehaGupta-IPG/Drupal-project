/**
 * @file
 * Translates a DropIntent into a server move + visible-state refresh.
 *
 * Server is the only source of truth. We POST to /move with no DOM mutation up
 * front; on success we re-fetch the affected levels (from-parent and to-parent)
 * and rebuild their rows in place. The whole class of "client moved the DOM but
 * forgot to update attribute X" bugs goes away — any attribute the row needs
 * is set by row-builder from the server's JSON.
 *
 * Trade-off: a moved row's previously-expanded descendants collapse after the
 * refresh. Acceptable for v1 — replay-expansion can be added later.
 *
 * The move response shape is {ok: boolean, weight?, code?, message?}.
 *
 * Exposes Drupal.menuTreeUi.drag.commitMove.
 */

((Drupal) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};
  Drupal.menuTreeUi.drag = Drupal.menuTreeUi.drag || {};
  const { childRows, descendantRows, findRow, focusRow, nextSiblingRow } =
    Drupal.menuTreeUi.treeNav;

  const ROW = 'tr[role="row"]';

  /**
   * Announces a message through Drupal's live-region announcer.
   *
   * @param {string} message
   *   The message.
   * @param {('polite'|'assertive')} priority
   *   The live-region priority.
   */
  function announce(message, priority) {
    if (Drupal.announce) {
      Drupal.announce(message, priority);
    }
  }

  /**
   * Resolves the parent id the moved row should end up under.
   *
   * @param {object} intent
   *   The DropIntent.
   *
   * @return {string}
   *   The destination parent plugin ID, or ''.
   */
  function resolveNewParentId(intent) {
    if (intent.kind === 'child') {
      return intent.target.dataset.id ?? '';
    }
    return intent.target.dataset.parentId ?? '';
  }

  /**
   * Resolves the before_id payload for /move.
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
   * Builds a fresh expand/collapse toggle button.
   *
   * @param {string} title
   *   The link title, for the aria-label.
   *
   * @return {HTMLButtonElement}
   *   The toggle button.
   */
  function makeToggleButton(title) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'menu-tree-ui-row__toggle';
    button.setAttribute('aria-label', `Toggle children of ${title}`);
    const chevron = document.createElement('span');
    chevron.setAttribute('aria-hidden', 'true');
    chevron.className = 'menu-tree-ui-row__chevron';
    chevron.textContent = '▸';
    button.appendChild(chevron);
    return button;
  }

  /**
   * Builds a toggle spacer (used when a parent becomes a leaf).
   *
   * @return {HTMLSpanElement}
   *   The spacer span.
   */
  function makeToggleSpacer() {
    const spacer = document.createElement('span');
    spacer.className =
      'menu-tree-ui-row__toggle menu-tree-ui-row__toggle--spacer';
    spacer.setAttribute('aria-hidden', 'true');
    return spacer;
  }

  /**
   * Syncs a parent row's "parent or leaf" attributes to whatever its direct
   * child rows now actually are.
   *
   * /children?parent=X returns X's children, not X's metadata, so this is the
   * one piece of state derived client-side after a refresh. Skipped when
   * parentId is '' (root has no enclosing row) or the row isn't in the DOM.
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
    const hasChildrenNow = childRows(row).length > 0;
    row.dataset.hasChildren = hasChildrenNow ? 'true' : 'false';

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
        const button = makeToggleButton(title);
        if (existing) {
          existing.replaceWith(button);
        } else {
          name.prepend(button);
        }
      }
    } else {
      row.removeAttribute('aria-expanded');
      if (existing && existing.tagName.toLowerCase() === 'button') {
        existing.replaceWith(makeToggleSpacer());
      }
      // On becoming a leaf, remove any stale descendant rows left behind.
      descendantRows(row).forEach((r) => r.remove());
      delete row.dataset.loaded;
    }
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
   * @param {Drupal.menuTreeUi.ExpandManager} expand
   *   The expand manager, for destination auto-expand + group refreshes.
   * @param {?HTMLTableElement} tree
   *   The root treegrid element.
   * @param {string} movedId
   *   The moved row's plugin ID.
   * @param {string} [csrfToken]
   *   The X-CSRF-Token value.
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
        announce('Could not open destination — move cancelled.', 'assertive');
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
        announce(body.message ?? 'Move failed.', 'assertive');
        return;
      }
    } catch (e) {
      // eslint-disable-next-line no-console
      console.error('[menu-tree] move fetch failed', e);
      announce('Network error — move cancelled.', 'assertive');
      return;
    }

    // Server confirmed. Re-fetch the visible state of both affected groups.
    // When from === to (sibling reorder under the same parent) the second
    // refresh is a no-op but harmless.
    await expand.refresh(oldParentId);
    if (newParentId !== oldParentId) {
      await expand.refresh(newParentId);
    }

    applyParentMetadata(oldParentId);
    applyParentMetadata(newParentId);

    // row and intent.target are detached after refresh — re-find by id.
    if (tree) {
      const fresh = findRow(tree, movedId);
      if (fresh) {
        focusRow(tree, fresh);
      }
    }

    announce('Menu link moved.', 'polite');
    // Tell the working-copy action bar a change was recorded so it can reveal
    // the unsaved notice (the bar lives outside the <menu-tree> element).
    document.dispatchEvent(new CustomEvent('menu-tree-ui:changed'));
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
   * @param {Drupal.menuTreeUi.ExpandManager} expand
   *   The expand manager.
   * @param {string} [csrfToken]
   *   The X-CSRF-Token value.
   *
   * @return {Promise<void>}
   *   Resolves once the commit completes.
   */
  async function commitMove(row, intent, moveUrl, expand, csrfToken) {
    const movedId = row.dataset.id;
    if (!movedId) {
      return;
    }

    const tree = row.closest('table[role="treegrid"]');
    const host = tree?.closest('menu-tree') ?? null;
    host?.setAttribute('data-mtu-moving', 'true');
    try {
      await runCommit(row, intent, moveUrl, expand, tree, movedId, csrfToken);
    } finally {
      host?.removeAttribute('data-mtu-moving');
    }
  }

  Drupal.menuTreeUi.drag.commitMove = commitMove;
  // Exported so keyboard move mode can resolve an intent to {new_parent,
  // before_id} for its no-op-drop guard, without duplicating the logic.
  Drupal.menuTreeUi.drag.resolveNewParentId = resolveNewParentId;
  Drupal.menuTreeUi.drag.resolveBeforeId = resolveBeforeId;
})(Drupal);
