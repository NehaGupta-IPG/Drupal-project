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
            // Pass only the valid dataset attributes to the filter class.
            const validKeys = Object.keys(Drupal.ListFilter.defaultOptions);
            sanitizedDataset = Object.fromEntries(
              Object.entries(input.dataset).filter(([key]) => validKeys.includes(key))
            );

            // Consolidate the options for the list filter.
            const options = {
              input,
              ...sanitizedDataset,
              searchStart: input.dataset.searchStart === 'true',
              minLength:
                parseInt(input.dataset.minLength, 10) ||
                defaultOptions.minLength,
            };

            // Activate the list filter.
            const filter = new Drupal.ListFilter(options);
            filter.filter();
          },
          { once: true },
        );
      });
    },
  };
})(Drupal, Drupal.debounce, once, window.slugify);
