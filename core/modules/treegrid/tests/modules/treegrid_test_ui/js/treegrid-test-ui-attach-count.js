/**
 * @file
 * A behavior without once() that records the attach calls reaching each row.
 *
 * window.treegridTestUiAttaches maps a row id to one entry per attach call
 * whose context contains the row: { staging } is TRUE when the context was
 * the staging tbody.
 */

((Drupal) => {
  window.treegridTestUiAttaches = {};

  Drupal.behaviors.treegridTestUiAttachCount = {
    attach(context) {
      if (!(context instanceof Element)) {
        return;
      }
      const staging = context.matches('[data-treegrid-staging] > tbody');
      const rows = [...context.querySelectorAll('tr[role="row"]')];
      if (context.matches('tr[role="row"]')) {
        rows.push(context);
      }
      rows.forEach((row) => {
        const { id } = row.dataset;
        window.treegridTestUiAttaches[id] =
          window.treegridTestUiAttaches[id] ?? [];
        window.treegridTestUiAttaches[id].push({ staging });
      });
    },
  };
})(Drupal);
