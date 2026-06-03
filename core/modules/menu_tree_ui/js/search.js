/**
 * @file
 * Dismissal behavior for the htmx-driven search results popover.
 *
 * htmx swaps results into #menu-tree-ui-search-results on keyup; the popover
 * self-hides when empty (CSS `:not(:has(*))`). This module empties it - without
 * touching the typed query - so the popover goes away when the user is done
 * with it: after selecting a result, on Escape, or on a click outside the
 * search box. The query text stays so the search can be resumed or refined.
 *
 * Exposes Drupal.menuTreeUi.attachSearchPanel.
 */

((Drupal) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};

  /**
   * Wires popover dismissal for one search container.
   *
   * @param {HTMLElement} container
   *   The .menu-tree-ui__search-container (or any ancestor of the input and
   *   results) to wire.
   *
   * @return {function()}
   *   A teardown function that removes the listeners.
   */
  function attachSearchPanel(container) {
    const input = container.querySelector('.menu-tree-ui__search-input');
    const results = container.querySelector('.menu-tree-ui__search-results');
    if (!input || !results) {
      return () => {};
    }

    // Empties the popover (CSS hides an empty popover). Leaves input.value.
    const clear = () => {
      results.replaceChildren();
    };

    // A result link/button was activated. Defer the clear so the click's other
    // handlers (jump-to.js navigates to the row) read the result first.
    const onResultsClick = (event) => {
      if (
        event.target instanceof Element &&
        event.target.closest('[data-mtu-jump-id], a')
      ) {
        window.setTimeout(clear, 0);
      }
    };

    // Escape hides the popover but keeps the query - preventDefault stops the
    // browser's native "clear the search field" behavior.
    const onKeyDown = (event) => {
      if (event.key === 'Escape' && results.firstElementChild) {
        event.preventDefault();
        clear();
      }
    };

    // A click anywhere outside the search box dismisses the popover.
    const onDocumentClick = (event) => {
      if (event.target instanceof Node && !container.contains(event.target)) {
        clear();
      }
    };

    results.addEventListener('click', onResultsClick);
    input.addEventListener('keydown', onKeyDown);
    document.addEventListener('click', onDocumentClick);

    return () => {
      results.removeEventListener('click', onResultsClick);
      input.removeEventListener('keydown', onKeyDown);
      document.removeEventListener('click', onDocumentClick);
    };
  }

  Drupal.menuTreeUi.attachSearchPanel = attachSearchPanel;
})(Drupal);
