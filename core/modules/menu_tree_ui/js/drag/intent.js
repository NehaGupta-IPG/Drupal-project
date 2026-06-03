/**
 * @file
 * Drop-intent computation for tree drag.
 *
 * Splits the target row's HEADER (just the .menu-tree-ui-row__content box, not
 * the whole subtree height) into three zones:
 *   top 30% → before
 *   middle  → child
 *   bottom 30% → after
 *
 * Using the header instead of the row's full bounding rect means parents with
 * many expanded children still have a usable middle zone — otherwise "child of
 * this parent" would only be hittable on a 0.4-of-very-tall sliver.
 *
 * Drop-as-child on a collapsed parent stays as 'child' — commitMove is
 * responsible for expanding the destination before applying the move, so the
 * dropped row lands visibly inside.
 *
 * A DropIntent is {kind: ('before'|'after'|'child'), target: HTMLElement}.
 *
 * Exposes Drupal.menuTreeUi.drag.{computeIntent, rowHeaderRect, sameIntent}.
 */

((Drupal) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};
  Drupal.menuTreeUi.drag = Drupal.menuTreeUi.drag || {};

  const CHILD_ZONE_FROM = 0.3;
  const CHILD_ZONE_TO = 0.7;

  /**
   * The row's header box — the visible content row, not the whole li (which
   * includes any expanded children's subtree).
   *
   * @param {HTMLElement} row
   *   The row.
   *
   * @return {DOMRect}
   *   The header bounding rect.
   */
  function rowHeaderRect(row) {
    const content = row.querySelector(':scope > .menu-tree-ui-row__content');
    return content
      ? content.getBoundingClientRect()
      : row.getBoundingClientRect();
  }

  /**
   * Returns the drop intent for a pointer position over a target row.
   *
   * @param {HTMLElement} target
   *   The row under the pointer.
   * @param {number} y
   *   The pointer's clientY.
   *
   * @return {object}
   *   The DropIntent.
   */
  function computeIntent(target, y) {
    const headerRect = rowHeaderRect(target);
    const rel = (y - headerRect.top) / headerRect.height;

    if (rel < CHILD_ZONE_FROM) {
      return { kind: 'before', target };
    }
    if (rel > CHILD_ZONE_TO) {
      return { kind: 'after', target };
    }
    return { kind: 'child', target };
  }

  /**
   * Equality check for two intents, to skip redundant re-renders.
   *
   * @param {?object} a
   *   The first intent, or null.
   * @param {?object} b
   *   The second intent, or null.
   *
   * @return {boolean}
   *   True if the intents are equivalent.
   */
  function sameIntent(a, b) {
    if (a === null || b === null) {
      return a === b;
    }
    return a.kind === b.kind && a.target === b.target;
  }

  Drupal.menuTreeUi.drag.rowHeaderRect = rowHeaderRect;
  Drupal.menuTreeUi.drag.computeIntent = computeIntent;
  Drupal.menuTreeUi.drag.sameIntent = sameIntent;
})(Drupal);
