/**
 * @file
 *  Exercises Drupal.deprecateSizzleSelector with a valid CSS selector and a
 *  jQuery-only (Sizzle) selector, so tests can assert that only the latter
 *  triggers a deprecation warning.
 */
(function ({ deprecateSizzleSelector, behaviors }) {
  behaviors.testSizzleDeprecation = {
    attach: () => {
      // A valid CSS selector must not trigger a deprecation warning.
      deprecateSizzleSelector('.valid-selector');
      // A jQuery-only (Sizzle) selector must trigger a deprecation warning,
      // because document.querySelector() throws a SyntaxError for it.
      deprecateSizzleSelector(':input');
    },
  };
})(Drupal);
