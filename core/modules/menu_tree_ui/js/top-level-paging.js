/**
 * @file
 * Streams the rest of a truncated top level in as the editor scrolls.
 *
 * The server renders only the first page of top-level rows (see
 * MenuTreeUiConstants::TOP_LEVEL_PAGE_SIZE) so a very wide top level does not
 * make first paint expensive. When the top level was truncated, an
 * IntersectionObserver sentinel below the tree fetches the next page from the
 * children endpoint (parent empty, with an offset) through htmx and appends the
 * rows as top-level siblings, until the server reports no more remain.
 *
 * Exposes Drupal.menuTreeUi.attachTopLevelPaging.
 */

((Drupal, htmx) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};
  const { setRowControlsActive } = Drupal.treegrid.treeNav;

  const ROW = 'tr[role="row"]';

  /**
   * Wires scroll-triggered paging for a truncated top level.
   *
   * @param {HTMLElement} element
   *   The upgraded <drupal-treegrid> element (element.tree / element.rowWindow
   *   are set).
   *
   * @return {function()}
   *   A teardown function that stops observing and removes the sentinel.
   */
  function attachTopLevelPaging(element) {
    const total = parseInt(element.dataset.treegridTopTotal, 10) || 0;
    const pageSize = parseInt(element.dataset.treegridPageSize, 10) || 0;
    const { childrenUrl } = element.dataset;
    const { tree } = element;
    const noop = () => {};
    if (!tree || !childrenUrl || pageSize <= 0) {
      return noop;
    }
    const tbody = tree.querySelector('tbody');
    if (!tbody) {
      return noop;
    }
    let offset = tbody.querySelectorAll(`${ROW}[data-parent-id=""]`).length;
    if (offset >= total) {
      return noop;
    }

    let loading = false;
    let done = false;

    // A sentinel after the table (not a table row, so tree navigation ignores
    // it); scrolling it into view triggers the next fetch.
    const sentinel = document.createElement('div');
    sentinel.className = 'menu-tree-ui__paging-sentinel';
    sentinel.setAttribute('aria-hidden', 'true');
    element.appendChild(sentinel);

    // Reassigned to disconnect the observer once it exists, so teardown()
    // can be defined (and called from loadNext) before the observer is.
    let stopObserving = noop;

    /**
     * Stops paging: disconnect the observer and drop the sentinel.
     */
    function teardown() {
      done = true;
      stopObserving();
      sentinel.remove();
    }

    /**
     * Fetches and appends the next page of top-level rows.
     *
     * @return {Promise<void>}
     *   Resolves once the page is appended (or paging stopped on failure/end).
     */
    async function loadNext() {
      if (loading || done) {
        return;
      }
      loading = true;
      let ctx = null;
      let rejected = false;
      let errored = false;
      let rows = [];
      const onBeforeResponse = (event) => {
        if (event.detail.ctx?.sourceElement !== tbody) {
          return;
        }
        ctx = event.detail.ctx;
        const { response } = ctx;
        if (
          response.status >= 400 ||
          !response.headers.has('X-Treegrid-Rows')
        ) {
          rejected = true;
          event.preventDefault();
        }
      };
      const onError = (event) => {
        if (event.detail.ctx?.sourceElement === tbody) {
          errored = true;
        }
      };
      // htmx events bubble, so a swap inside a row would reach this listener.
      const onSettle = (event) => {
        if (event.target === tbody) {
          rows = event.detail.newContent.filter((node) => node.matches?.(ROW));
        }
      };
      document.addEventListener('htmx:before:response', onBeforeResponse);
      document.addEventListener('htmx:error', onError);
      tbody.addEventListener('htmx:after:settle', onSettle);
      try {
        const url = new URL(childrenUrl, window.location.origin);
        url.searchParams.set('offset', String(offset));
        await htmx.ajax('GET', url.toString(), {
          target: tbody,
          swap: 'beforeend ignoreTitle:true',
          select: '[data-treegrid-rows] > tr',
        });
        if (!ctx || rejected || errored) {
          teardown();
          return;
        }
        rows.forEach((row, i) => {
          // Number the rows against the full level, so a screen reader
          // announces "row N of total", not "N of page".
          row.setAttribute('aria-posinset', String(offset + i + 1));
          row.setAttribute('aria-setsize', String(total));
          row.setAttribute('tabindex', '-1');
          setRowControlsActive(row, false);
          element.rowWindow?.observe(row);
        });
        offset += rows.length;
        if (!ctx.response.headers.has('X-Treegrid-Next-Id')) {
          teardown();
        }
      } catch {
        teardown();
      } finally {
        document.removeEventListener('htmx:before:response', onBeforeResponse);
        document.removeEventListener('htmx:error', onError);
        tbody.removeEventListener('htmx:after:settle', onSettle);
        loading = false;
      }
    }

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
          loadNext();
        }
      },
      { root: null, rootMargin: '400px 0px' },
    );
    observer.observe(sentinel);
    stopObserving = () => observer.disconnect();

    return teardown;
  }

  Drupal.menuTreeUi.attachTopLevelPaging = attachTopLevelPaging;
})(Drupal, htmx);
