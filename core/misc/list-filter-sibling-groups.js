/**
 * @file
 * Extends the list filter script to work groups that are antecedent siblings of
 * items, rather than parent elements.
 *
 * For example, a <table> where some rows are filterable items, and some rows
 * are headings.
 */

(function ($, Drupal, drupalSettings) {
  /**
   * Add filtering for groups that are siblings of their items.
   */
  $.extend(Drupal.listFilter.prototype, {
    /**
     * {@inheritdoc}
     */
    getRowGroup($row, $groups) {
      // Go through previous siblings of the row, until we find a previous sibling
      // that is a group.
      let $currentSibling = $row;
      do {
        $currentSibling = $currentSibling.prev();
      } while (!$groups.is($currentSibling) && $currentSibling.length > 0);

      if ($currentSibling.length === 0) {
        return null;
      }

      return $groups.index($currentSibling);
    },
  });
})(jQuery, Drupal, drupalSettings);
