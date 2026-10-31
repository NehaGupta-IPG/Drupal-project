/**
 * @file
 * A minimal treegrid consumer that turns on reordering.
 *
 * From a behavior it calls Drupal.treegridReorder.attach() on each element,
 * following the documented pattern. It exists so the reorder JS is tested within
 * treegrid_reorder itself, with no other consuming module involved.
 */

((Drupal, once) => {
  /**
   * Creates the callback that syncs a parent row's open state for one tree.
   *
   * The row's data-has-children decides which of the toggle and the spacer
   * shows (see the stylesheet).
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element the callback serves.
   *
   * @return {function(string): void}
   *   The callback, taking the parent id.
   */
  function createParentMetadataUpdater(element) {
    return (parentId) => {
      if (parentId === '') {
        return;
      }
      const row = element.tree.querySelector(
        `tr[role="row"][data-id="${CSS.escape(parentId)}"]`,
      );
      if (!row) {
        return;
      }
      if (row.dataset.hasChildren === 'true') {
        row.setAttribute('aria-expanded', 'true');
      } else {
        row.removeAttribute('aria-expanded');
        element.clearChildren(row);
      }
    };
  }

  const reorders = new WeakMap();

  Drupal.behaviors.treegridReorderTest = {
    attach(context) {
      once(
        'treegrid-reorder-test',
        'drupal-treegrid[data-provider="treegrid_reorder_test"]',
        context,
      ).forEach(async (element) => {
        reorders.set(
          element,
          Drupal.treegridReorder.attach(element, {
            applyParentMetadata: createParentMetadataUpdater(element),
            container: element.closest('.reorder-test-container'),
          }),
        );
        // A key handler of the consumer's own, registered after attach(), so a
        // test can see which key events reach it.
        element.addKeyHandler(() => {
          window.reorderTestKeyEvents = (window.reorderTestKeyEvents ?? 0) + 1;
          return false;
        });
        await element.ready;
        await Drupal.treegrid.csrfToken(element);
        if (!element.isConnected || !reorders.has(element)) {
          return;
        }
        element.setAttribute('data-test-reorder-wired', '');
      });
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once
        .remove(
          'treegrid-reorder-test',
          'drupal-treegrid[data-provider="treegrid_reorder_test"]',
          context,
        )
        .forEach((element) => {
          reorders.get(element)?.detach();
          reorders.delete(element);
        });
    },
  };
})(Drupal, once);
