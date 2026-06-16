/**
 * @file
 * Drop-intent computation for tree drag.
 *
 * Splits the target row into three zones:
 *   top 30% → before
 *   middle  → child
 *   bottom 30% → after
 *
 * The row IS its own header now: a <tr> holds only its own cells, never its
 * subtree (children are sibling rows), so the row's bounding rect is the header
 * box and the middle "child" zone stays usable on parents with many expanded
 * children.
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
   * The row's header box — the <tr> rect, which holds only the row's own cells
   * (children are sibling rows, not nested in this one).
   *
   * @param {HTMLElement} row
   *   The row.
   *
   * @return {DOMRect}
   *   The header bounding rect.
   */
  function rowHeaderRect(row) {
    return row.getBoundingClientRect();
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
