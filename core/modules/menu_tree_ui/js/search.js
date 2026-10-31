/**
 * @file
 * Gate and dismissal behavior for the htmx-driven search results popover.
 *
 * htmx swaps results into #menu-tree-ui-search-results on keyup; the popover
 * self-hides when empty (CSS `:not(:has(*))`).
 *
 * A page-load behavior refuses any response that isn't search results (an error
 * status, or no X-Menu-Tree-Ui-Search header), so htmx loads none of its assets,
 * and empties the popover so earlier results don't stay up.
 *
 * attachSearchPanel() empties the popover - without touching the typed query -
 * so it goes away when the user is done with it: after selecting a result, on
 * Escape, or on a click outside the search box. The query text stays so the
 * search can be resumed or refined.
 *
 * Exposes Drupal.menuTreeUi.attachSearchPanel.
 */

((Drupal, once) => {
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

  // The gate lives as long as the page. It has no detach: each search swaps
  // into the popover, which makes core trigger the unload of the search
  // container that holds the input, and a detach would drop the gate then.
  Drupal.behaviors.menuTreeUiSearchGate = {
    attach(context) {
      once(
        'menu-tree-ui-search-gate',
        '.menu-tree-ui__search-input',
        context,
      ).forEach((input) => {
        document.addEventListener('htmx:before:response', (event) => {
          const ctx = event.detail.ctx;
          if (ctx?.sourceElement !== input) {
            return;
          }
          const { response } = ctx;
          if (
            response.status >= 400 ||
            !response.headers.has('X-Menu-Tree-Ui-Search')
          ) {
            event.preventDefault();
            input
              .closest('.menu-tree-ui__search-container')
              ?.querySelector('.menu-tree-ui__search-results')
              ?.replaceChildren();
          }
        });
      });
    },
  };
})(Drupal, once);
