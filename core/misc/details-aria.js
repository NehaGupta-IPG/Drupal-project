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
      $(once('detailsAria', 'body')).on(
        'click.detailsAria',
        'summary',
        (event) => {
          const $summary = $(event.currentTarget);

          // ✅ Correct logic: read the real current open state.
          const open = event.currentTarget.parentNode.hasAttribute('open')
            ? 'true'
            : 'false';

          $summary.attr({
            'aria-expanded': open,
          });
        },
      );
    },
  };

})(jQuery, Drupal);
