/**
 * @file
 * List filter for admin tables and lists.
 */

(function (Drupal, debounce, once, ListFilter) {
  Drupal.behaviors.listFilterOverride = {
    attach(context) {
      once(
        'list-filter-override',
        '.table-filter-text[data-auto-activate="true"]',
        context,
      ).forEach((input) => {
        input.addEventListener('listFilter:searchingFinished', (e) => {
          console.warn(`searching finished now...`, e.detail);
          console.warn(
            `The number of results: ${e.detail.matched.length} whereas the number of unmatched rows is ${e.detail.unmatched.length}`,
          );
        });
      });
    },
  };

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
            const defaultSettings = ListFilter.DEFAULT_SETTINGS;
            const validKeys = Object.keys(defaultSettings);
            const sanitizedDataset = Object.fromEntries(
              Object.entries(input.dataset).filter(([key]) =>
                validKeys.includes(key),
              ),
            );

            // Consolidate the settings for the list filter.
            const settings = {
              input,
              ...sanitizedDataset,
              searchStart: input.dataset.searchStart === 'true',
              minLength:
                parseInt(input.dataset.minLength, 10) ||
                defaultSettings.minLength,
            };

            // Activate the list filter.
            const filter = new ListFilter(settings);
            filter.filter();
          },
          { once: true },
        );
      });
    },
  };
})(Drupal, Drupal.debounce, once, Drupal.ListFilter);
