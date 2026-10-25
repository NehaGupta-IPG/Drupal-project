/**
 * @file
 * A consumer behavior for the treegrid test page, following the documented
 * pattern: once(), await element.ready, check the tree is still wanted, set up.
 *
 * The setup marks the element with data-test-setup.
 */

((Drupal, once) => {
  const SELECTOR = 'drupal-treegrid[data-provider="treegrid_test_ui"]';
  const records = new WeakSet();

  Drupal.behaviors.treegridTestUiConsumer = {
    attach(context) {
      once('treegrid-test-ui-consumer', SELECTOR, context).forEach(
        async (element) => {
          records.add(element);
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
