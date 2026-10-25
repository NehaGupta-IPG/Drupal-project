/**
 * @file
 * Jump-to from a search result.
 *
 * Search results live in the htmx-injected sidebar, outside <drupal-treegrid>. The
 * listener attaches at document level so it works across swaps.
 *
 * Exposes Drupal.menuTreeUi.attachJumpClickHandler.
 */

((Drupal) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};
  const { findRow, focusRow } = Drupal.treegrid.treeNav;

  const FLASH_MS = 1200;

  /**
   * Walks the ancestor chain (root-to-parent), expanding each level (lazy-
   * loading its children if needed), then scrolls and focuses the target.
   *
   * @param {object} ctx
   *   Context: {tree: HTMLTableElement, element: HTMLElement, reorder?:
   *   the object treegridReorder.attach() returned}.
   * @param {string} targetId
   *   The link plugin ID to jump to.
   * @param {string[]} pathIds
   *   Ancestor IDs from root to parent.
   *
   * @return {Promise<void>}
   *   Resolves once the jump completes (or aborts).
   */
  async function jumpTo(ctx, targetId, pathIds) {
    // Sequential expansion: each level must be open before the next is in DOM.
    // eslint-disable-next-line no-restricted-syntax
    for (const ancestorId of pathIds) {
      const row = findRow(ctx.tree, ancestorId);
      if (!row) {
        // eslint-disable-next-line no-console
        console.warn(
          '[menu-tree] jump aborted: ancestor not in DOM',
          ancestorId,
        );
        return;
      }
      if (row.getAttribute('aria-expanded') !== 'true') {
        // eslint-disable-next-line no-await-in-loop
        await ctx.element.expandRow(row);
      }
    }
    const target = findRow(ctx.tree, targetId);
    if (!target) {
      // eslint-disable-next-line no-console
      console.warn(
        '[menu-tree] jump target not found after expansion',
        targetId,
      );
      return;
    }
    target.scrollIntoView({ block: 'center', behavior: 'smooth' });
    // If a move is in progress, the editor searched for a destination: keep the
    // grab and re-aim the cursor at the jumped-to row instead of stealing focus.
    if (ctx.reorder?.isActive()) {
      ctx.reorder.aimAt(target);
    } else {
      focusRow(ctx.tree, target);
    }
    target.classList.add('menu-tree-ui-row--flash');
    window.setTimeout(() => {
      target.classList.remove('menu-tree-ui-row--flash');
    }, FLASH_MS);
  }

  /**
   * Wires document-level click handling for [data-mtu-jump-id].
   *
   * @param {object} ctx
   *   Context: {tree: HTMLTableElement, element: HTMLElement, reorder?: the
   *   object treegridReorder.attach() returned}.
   *
   * @return {function(MouseEvent): void}
   *   The bound handler, so the caller can remove it on teardown.
   */
  function attachJumpClickHandler(ctx) {
    const handler = (event) => {
      const { target } = event;
      if (!(target instanceof Element)) {
        return;
      }
      const trigger = target.closest('[data-mtu-jump-id]');
      if (!trigger) {
        return;
      }
      const targetId = trigger.dataset.mtuJumpId;
      if (!targetId) {
        return;
      }
      event.preventDefault();
      const pathAttr = trigger.dataset.mtuJumpPath ?? '';
      const path = pathAttr === '' ? [] : pathAttr.split(',');
      jumpTo(ctx, targetId, path);
    };
    document.addEventListener('click', handler);
    return handler;
  }

  Drupal.menuTreeUi.attachJumpClickHandler = attachJumpClickHandler;
})(Drupal);
