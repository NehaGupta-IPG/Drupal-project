/**
 * @file
 * List filter for admin tables and lists.
 */

(function (Drupal, debounce, once, slugify) {

  /**
   * Behavior to attach the list filter to elements.
   *
   * @namespace Drupal.behaviors.drupalListFilter
   */
  Drupal.behaviors.drupalListFilter = {
    attach(context) {
      once(
        'drupal-list-filter',
        '.table-filter-text[data-auto-activate="true"]',
        context,
      ).forEach((input) => {
        input.addEventListener(
          'focus',
          () => {
            const options = {
              input,
              ...input.dataset,
              searchStart: input.dataset.searchStart === 'true',
              minLength:
                parseInt(input.dataset.minLength, 10) ||
                defaultOptions.minLength,
            };

            // Usage example (in Drupal behavior):
            const filter = new Drupal.ListFilter();
            filter.filter(options);
          },
          { once: true },
        );
      });
    },
  };
})(Drupal, Drupal.debounce, once, window.slugify);
