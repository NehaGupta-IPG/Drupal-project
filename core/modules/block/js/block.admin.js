/**
 * @file
 * Block admin behaviors.
 */

(function ($, Drupal, debounce, once) {
  /**
   * Highlights the block that was just placed into the block listing.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior for the block placement highlighting.
   */
  Drupal.behaviors.blockHighlightPlacement = {
    attach(context, settings) {
      // Ensure that the block we are attempting to scroll to actually exists.
      if (settings.blockPlacement && $('.js-block-placed').length) {
        once(
          'block-highlight',
          '[data-drupal-selector="edit-blocks"]',
          context,
        ).forEach((container) => {
          const $container = $(container);
          window.scrollTo({
            top:
              $('.js-block-placed').offset().top -
              $container.offset().top +
              $container.scrollTop(),
            behavior: 'smooth',
          });
        });
      }
    },
  };
})(jQuery, Drupal, Drupal.debounce, once);
