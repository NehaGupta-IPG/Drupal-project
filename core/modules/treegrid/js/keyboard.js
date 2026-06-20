/**
 * @file
 * WAI-ARIA tree keyboard pattern.
 *
 * Pure handler — takes the event plus a context object of callbacks. Shift+
 * Arrow sibling reorder lives in reorder-keys.js; this file only covers
 * navigation and expand/collapse keys.
 *
 * Exposes Drupal.treegrid.handleKeyDown.
 */

((Drupal) => {
  Drupal.treegrid = Drupal.treegrid || {};
  const {
    childRows,
    findRow,
    firstVisibleRow,
    focusRow,
    lastVisibleRow,
    nextVisibleRow,
    previousVisibleRow,
    rowOf,
  } = Drupal.treegrid.treeNav;

  /**
   * Handles a keydown on the tree.
   *
   * @param {KeyboardEvent} event
   *   The keydown event.
   * @param {object} ctx
   *   Context: {tree: HTMLTableElement, expand: ExpandManager,
   *   reorder: function(HTMLElement, ('up'|'down')): boolean}.
   */
  function handleKeyDown(event, ctx) {
    const row = rowOf(event.target);
    if (!row) {
      return;
    }

    if (
      event.shiftKey &&
      (event.key === 'ArrowUp' || event.key === 'ArrowDown')
    ) {
      const handled = ctx.reorder(row, event.key === 'ArrowUp' ? 'up' : 'down');
      if (handled) {
        event.preventDefault();
      }
      return;
    }

    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault();
        focusRow(ctx.tree, nextVisibleRow(ctx.tree, row));
        break;
      case 'ArrowUp':
        event.preventDefault();
        focusRow(ctx.tree, previousVisibleRow(ctx.tree, row));
        break;
      case 'ArrowRight': {
        event.preventDefault();
        if (row.dataset.hasChildren !== 'true') {
          break;
        }
        if (row.getAttribute('aria-expanded') === 'false') {
          ctx.expand.expand(row);
        } else {
          focusRow(ctx.tree, childRows(row)[0] ?? null);
        }
        break;
      }
      case 'ArrowLeft': {
        event.preventDefault();
        if (row.getAttribute('aria-expanded') === 'true') {
          ctx.expand.collapse(row);
        } else {
          focusRow(ctx.tree, findRow(ctx.tree, row.dataset.parentId));
        }
        break;
      }
      case 'Home':
        event.preventDefault();
        focusRow(ctx.tree, firstVisibleRow(ctx.tree));
        break;
      case 'End':
        event.preventDefault();
        focusRow(ctx.tree, lastVisibleRow(ctx.tree));
        break;
      case 'Enter':
        // Enter toggles expand/collapse on parents. Space is reserved for the
        // grab/select move mode and is handled upstream in menu-tree.js.
        if (row.dataset.hasChildren === 'true') {
          event.preventDefault();
          ctx.expand.toggle(row);
        }
        break;
      default:
        break;
    }
  }

  Drupal.treegrid.handleKeyDown = handleKeyDown;
})(Drupal);
