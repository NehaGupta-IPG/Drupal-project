/**
 * @file
 * IntersectionObserver-based row windowing for the menu tree.
 *
 * Strategy: every row is a normal DOM node, but rows far outside the viewport
 * get `data-virtualised="true"`, which the CSS uses to skip rendering the row
 * content (and reserve a fixed height). The browser's own `content-visibility:
 * auto` carries most of the load — this layer also handles stricter cases like
 * a subtree with thousands of siblings, where we want off-screen content to
 * cost ~nothing.
 *
 * Why not pure content-visibility? Its heuristic still maintains the content's
 * layout box, and for very long lists the cumulative box-allocation cost is
 * non-zero. Forcing `visibility:hidden` + fixed height via this windowing is a
 * hard floor.
 *
 * Exposes Drupal.menuTreeUi.RowWindow.
 */

((Drupal) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};

  /**
   * Flags rows as virtualised when they leave the preload zone.
   *
   * @param {IntersectionObserverEntry[]} entries
   *   The observer entries.
   */
  function onIntersect(entries) {
    entries.forEach((entry) => {
      const row = entry.target;
      if (entry.isIntersecting) {
        delete row.dataset.virtualised;
      } else {
        row.dataset.virtualised = 'true';
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
     *   Preload buffer for the IntersectionObserver.
     */
    constructor(root, rootMargin = '400px 0px') {
      this.observer = new IntersectionObserver(onIntersect, {
        root: findScrollParent(root),
        rootMargin,
        threshold: 0,
      });
    }

    /**
     * Starts tracking a row's visibility.
     *
     * Deliberately does not pre-stamp `data-virtualised="true"`: the observer
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
      delete row.dataset.virtualised;
    }

    /**
     * Tears down the observer when the host element is removed.
     */
    disconnect() {
      this.observer.disconnect();
    }
  }

  Drupal.menuTreeUi.RowWindow = RowWindow;
})(Drupal);
