/**
 * @file
 * Attaches the ListFilter behavior to admin tables and lists in Drupal.
 *
 * This file does not define the ListFilter class itself, but provides a Drupal behavior
 * that instantiates the ListFilter library on specified elements. It is responsible for
 * initializing ListFilter with the appropriate options and wiring up the filtering UI.
 *
 * To customize ListFilter options from another module, listen for the
 * 'listFilter:settings' event on the input element **before** ListFilter is instantiated.
 *
 * @example <caption>Customizing ListFilter options from another module</caption>
 *   // Attach your event listener BEFORE ListFilter is instantiated:
 *   input.addEventListener('listFilter:settings', (event) => {
 *     event.detail.options.minLength = 5;
 *   });
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
(function ({ ListFilter, debounce, ...Drupal }, once) {
  /**
   * Behavior to attach the list filter to elements.
   *
   * @namespace Drupal.behaviors.drupalListFilter
   */
  Drupal.behaviors.drupalListFilter = {
    attach(context) {
      once('drupal-list-filter', '[data-list-filter]', context).forEach(
        (input) => {
          input.addEventListener(
            'focus',
            () => {
              // Activate the list filter.
              ListFilter.instances.set(input, new ListFilter(input));
            },
            { once: true },
          );
        },
      );
    },
    detach(context, settings, trigger) {
      if (trigger === 'unload') {
        once
          .remove('drupal-list-filter', '[data-list-filter]', context)
          .forEach((input) => {
            ListFilter.instances.get(input)?.destroy();
          });
      }
    },
  };
})(Drupal, once);
