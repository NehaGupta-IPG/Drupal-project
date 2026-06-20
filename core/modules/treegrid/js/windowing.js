/**
 * @file
 * IntersectionObserver-based row windowing for the menu tree.
 *
 * Strategy: every row is a normal DOM node, but rows far outside the viewport
 * get `data-virtualized="true"`, which the CSS uses to hide the row content
 * (visibility:hidden) and reserve a fixed height. The tree is a real table, so
 * every row is laid out regardless — this layer skips the *paint* of far-off
 * rows, which is what keeps a level with thousands of siblings cheap to scroll.
 *
 * (An earlier version paired this with `content-visibility: auto` to also skip
 * off-screen layout, but containment is disallowed on table rows, so the table
 * markup the admin theme styles wins out and layout cost is borne up front.)
 *
 * Exposes Drupal.treegrid.RowWindow.
 */

((Drupal) => {
  Drupal.treegrid = Drupal.treegrid || {};

  /**
   * Flags rows as virtualized when they leave the preload zone.
   *
   * @param {IntersectionObserverEntry[]} entries
   *   The observer entries.
   */
  function onIntersect(entries) {
    entries.forEach((entry) => {
      const row = entry.target;
      if (entry.isIntersecting) {
        delete row.dataset.virtualized;
      } else {
        row.dataset.virtualized = 'true';
      }
    });
  }

  /**
   * Walks up the DOM to the nearest scrollable ancestor, or the viewport.
   *
   * IntersectionObserver needs an ancestor element (or null for the viewport) —
   * passing the host element itself would only fire when rows leave the host,
   * which is wrong if the host scrolls inside a panel.
   *
   * @param {HTMLElement} el
   *   The element to start from.
   *
   * @return {?Element}
   *   The scroll parent, or null for the viewport.
   */
  function findScrollParent(el) {
    let node = el.parentElement;
    while (node) {
      const style = getComputedStyle(node);
      if (/(auto|scroll|overlay)/.test(style.overflowY)) {
        return node;
      }
      node = node.parentElement;
    }
    return null;
  }

  class RowWindow {
    /**
     * @param {HTMLElement} root
     *   The host element rows live inside.
     * @param {string} [rootMargin]
     *   Preload buffer for the IntersectionObserver — how far outside the
     *   viewport a row is un-hidden ahead of being scrolled to. Generous on
     *   purpose: on a large level the observer's callbacks lag, so a small
     *   buffer leaves rows blank for a moment as they scroll in. The cost of a
     *   wider buffer is only extra paint (every row is laid out regardless), so
     *   we trade some paint for smoother scrolling. A scrollbar drag that
     *   teleports past the buffer still flashes briefly; only true windowing
     *   would remove that.
     */
    constructor(root, rootMargin = '1500px 0px') {
      this.observer = new IntersectionObserver(onIntersect, {
        root: findScrollParent(root),
        rootMargin,
        threshold: 0,
      });
    }

    /**
     * Starts tracking a row's visibility.
     *
     * Deliberately does not pre-stamp `data-virtualized="true"`: the observer
     * callback fires asynchronously (next layout pass), so a freshly-observed
     * row would otherwise spend ~1 frame with its content hidden — visible to
     * the user as an empty row, especially right after a drag-drop refresh
     * re-observes every row in the affected groups. Let the observer be the
     * single source of truth for the flag.
     *
     * @param {HTMLElement} row
     *   The row to observe.
     */
    observe(row) {
      this.observer.observe(row);
    }

    /**
     * Stops tracking a row.
     *
     * @param {HTMLElement} row
     *   The row to stop observing.
     */
    unobserve(row) {
      this.observer.unobserve(row);
      delete row.dataset.virtualized;
    }

    /**
     * Tears down the observer when the host element is removed.
     */
    disconnect() {
      this.observer.disconnect();
    }
  }

  Drupal.treegrid.RowWindow = RowWindow;
})(Drupal);
