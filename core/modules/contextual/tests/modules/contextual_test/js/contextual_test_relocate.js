/**
 * @file
 * Simulates a script that moves a contextual region out of the document.
 */
(function (Drupal, once) {
  Drupal.behaviors.contextualTestRelocate = {
    attach(context) {
      once('contextual-test-relocate', '#region-second', context).forEach(
        (region) => {
          document.createDocumentFragment().appendChild(region);
        },
      );
    },
  };
})(Drupal, once);
