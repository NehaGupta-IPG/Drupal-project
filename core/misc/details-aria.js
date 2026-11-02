/**
 * @file
 * Add aria attribute handling for details and summary elements.
 */

(function ($, Drupal) {
  /**
   * Handles `aria-expanded` and `aria-pressed` attributes on details elements.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.detailsAria = {
    attach() {
      $(once('detailsAria', 'details')).on('toggle.detailsAria', (event) => {
        const $summary = $(event.currentTarget).find('summary');
        const open = event.currentTarget.open ? 'true' : 'false';

        $summary.attr({
          'aria-expanded': open,
        });
      });
    },
  };
})(jQuery, Drupal);
