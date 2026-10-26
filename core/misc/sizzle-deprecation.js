/**
 * @file
 * Provides helpers for deprecating jQuery-only selectors.
 * https://learn.jquery.com/performance/optimize-selectors/
 */

(function ($, Drupal, settings) {
  Drupal.deprecateSizzleSelector = function deprecateSizzleSelector(selector) {
    if (settings.suppressDeprecationErrors === false) {
      try {
        document.querySelector(selector);
      } catch (error) {
        if (error.name === 'SyntaxError') {
          Drupal.deprecationError({
            message: `Using jQuery-only selector "${selector}" is deprecated in drupal:11.4.0 and is removed from drupal:12.0.0.`,
          });
        }
      }
    }
    return $(selector);
  };
})(jQuery, Drupal, drupalSettings);
