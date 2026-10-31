/**
 * @file
 * A consumer behavior for the treegrid test page, following the documented
 * pattern: once(), await element.ready, check the tree is still wanted, set up.
 *
 * The setup marks the element with data-test-setup. At attach it also records
 * in window.treegridTestUiAttachState whether the element's ready promise was
 * still pending and whether data-treegrid-ready was set.
 */

((Drupal, once) => {
  const SELECTOR = 'drupal-treegrid[data-provider="treegrid_test_ui"]';
  const records = new WeakSet();

  Drupal.behaviors.treegridTestUiConsumer = {
    attach(context) {
      once('treegrid-test-ui-consumer', SELECTOR, context).forEach(
        async (element) => {
          records.add(element);
          const readyAttribute = element.hasAttribute('data-treegrid-ready');
          const readyPending =
            (await Promise.race([
              element.ready,
              Promise.resolve('pending'),
            ])) === 'pending';
          window.treegridTestUiAttachState =
            window.treegridTestUiAttachState ?? {
              readyPending,
              readyAttribute,
            };
          await element.ready;
          if (!element.isConnected || !records.has(element)) {
            return;
          }
          element.setAttribute('data-test-setup', '');
        },
      );
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once
        .remove('treegrid-test-ui-consumer', SELECTOR, context)
        .forEach((element) => {
          records.delete(element);
        });
    },
  };
})(Drupal, once);
