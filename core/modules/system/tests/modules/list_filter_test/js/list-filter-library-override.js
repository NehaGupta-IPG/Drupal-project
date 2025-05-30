/**
 * @file
 * test_filter_library behaviors.
 */
(function (Drupal) {
  Drupal.behaviors.listFilterOverride = {
    attach(context) {
      Drupal.listFilter.onFilter('.filter-input', context, {
        searchingFinished: function() {
          const result = document.querySelector(".js-filter-count");
          result.classList.remove("hidden");
          result.innerText = `Results found: ${this.matchedRows}`;
        },
      });
    },
  };
})(Drupal);
