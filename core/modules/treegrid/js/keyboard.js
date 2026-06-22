/**
 * @file
 * WAI-ARIA tree keyboard pattern.
 *
 * Pure handler — takes the event plus a context object. Covers navigation and
 * expand/collapse only; reorder and other features intercept keys ahead of this
 * through the element's key-handler registry (see <drupal-treegrid>).
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
   *   Context: {tree: HTMLTableElement, expand: ExpandManager}.
   */
  function handleKeyDown(event, ctx) {
    const row = rowOf(event.target);
    if (!row) {
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
          ctx.expand.expand(row, true);
        } else {
          focusRow(ctx.tree, childRows(row)[0] ?? null);
        }
        break;
      }
      case 'ArrowLeft': {
        event.preventDefault();
        if (row.getAttribute('aria-expanded') === 'true') {
          ctx.expand.collapse(row, true);
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
      case ' ':
        // Enter and Space toggle expand/collapse on parents. A feature (e.g.
        // reorder) may claim Space ahead of this via the key-handler registry;
        // it only reaches here when no feature consumed it.
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
