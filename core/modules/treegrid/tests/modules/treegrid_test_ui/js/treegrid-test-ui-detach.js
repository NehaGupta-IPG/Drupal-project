/**
 * @file
 * A behavior that records the rows whose behaviors are detached.
 *
 * window.treegridTestUiDetachedIds lists the ids of rows detached while they
 * sat inside the grid table, so a test can see that treegrid releases the rows
 * it removes. Staged rows are not in the grid table and are not recorded.
 */

((Drupal) => {
  window.treegridTestUiDetachedIds = [];

  Drupal.behaviors.treegridTestUiDetach = {
    detach(context) {
      if (
        context instanceof Element &&
        context.matches('tr[role="row"]') &&
        context.closest('table[role="treegrid"]')
      ) {
        window.treegridTestUiDetachedIds.push(context.dataset.id);
      }
    },
  };
})(Drupal);
