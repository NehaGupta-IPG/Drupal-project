/**
 * @file
 * Attaches the ListFilter behavior to admin tables and lists in Drupal.
 *
 * This file does not define the ListFilter class itself, but provides a Drupal behavior
 * that instantiates the ListFilter library on specified elements. It is responsible for
 * initializing ListFilter with the appropriate options and wiring up the filtering UI.
 *
 * To customize ListFilter options from another module, listen for the
 * 'listFilter:options-override' event on the input element **before** ListFilter is instantiated.
 *
 * @example <caption>Customizing ListFilter options from another module</caption>
 *   // Attach your event listener BEFORE ListFilter is instantiated:
 *   input.addEventListener('listFilter:options-override', (event) => {
 *     event.detail.options.minLength = 5;
 *     event.detail.options.hideElement = (el) => { el.style.display = 'none'; };
 *   });
 *
 *   // Then instantiate ListFilter (typically done by this behavior):
 *   const filter = new Drupal.ListFilter({
 *     input: document.querySelector('#my-input'),
 *     containerSelector: '#my-table',
 *     itemsSelector: 'tr',
 *     singular: 'item found',
 *     plural: 'items found',
 *   });
 *   filter.filter();
 *
 * @example <caption>Listening for search completion</caption>
 *   // Listen for the 'listFilter:searchingFinished' event to react to filtering results:
 *   input.addEventListener('listFilter:searchingFinished', (event) => {
 *     const { matched, unmatched } = event.detail;
 *     Drupal.announce(
 *       Drupal.formatPlural(
 *         matched.length,
 *         '1 item found',
 *         '@matched items found; @unmatched items not found',
 *         {
 *           '@matched': matched.length,
 *           '@unmatched': unmatched.length,
 *         }
 *       )
 *     );
 *   });
 */

(function (Drupal, debounce, once, ListFilter) {
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
