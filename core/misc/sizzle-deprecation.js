/**
 * @file
 * Provides helpers for deprecating jQuery-only selectors.
 * https://learn.jquery.com/performance/optimize-selectors/
 */

(function ($, Drupal) {
  Drupal.deprecateSizzleSelector = function deprecateSizzleSelector(selector) {
    try {
      document.querySelector(selector);
    } catch (error) {
      if (error instanceof SyntaxError) {
        Drupal.deprecationError({
          message: `Using jQuery-only selector "${selector}" is deprecated in 11.4.0 and is removed from Drupal:12.0.0.`,
        });
      }
    }
    return $(selector);
  };
})(jQuery, Drupal);
