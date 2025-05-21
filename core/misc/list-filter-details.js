/**
 * @file
 * Extends the list filter script to work with <details> elements.
 */

(function ($, Drupal, drupalSettings) {
  /**
   * Add filtering for <details> elements to the listFilter object.
   */
  $.extend(Drupal.listFilter.prototype, {
    /**
     * {@inheritdoc}
     */
    preFilter() {
      // Before filtering, open all <details> to be able to use ':visible'.
      // Mark the <details> elements that were closed before filtering, so
      // they can be closed again when filtering is removed.
      this.$groups
        .not('[open]')
        .attr('data-drupal-system-state', 'forced-open')
        .attr('open', true);
    },

    /**
     * {@inheritdoc}
     */
    showAllGroups() {
      // Return <details> elements that had been closed before filtering
      // to a closed state.
      this.$groups
        .filter('[data-drupal-system-state="forced-open"]')
        .removeAttr('data-drupal-system-state')
        .attr('open', false);

      // Show all groups.
      this.$groups.show();
    },
  });
})(jQuery, Drupal, drupalSettings);
