/**
 * @file
 * test_filter_library behaviors.
 */
(function (Drupal) {
  Drupal.behaviors.listFilterOverride = {
    attach(context) {
      // Drupal.listFilter.onFilter('.filter-input', context, {
      //   searchingFinished: function() {
      //     const result = document.querySelector(".js-filter-count");
      //     result.classList.remove("hidden");
      //     result.innerText = `Results found: ${this.matchedRows}`;
      //   },
      // });
      const result = document.querySelector('.js-filter-count');
      once('list-filter-override', '.filter-input', context).forEach(
        (input) => {
          input.addEventListener('listFilter:options', (e) => {
            e.detail.options.searchingFinished = function (matchedRows) {
              result.classList.remove('hidden');
              result.innerText = `Results found: ${matchedRows}`;
            };
          });
        },
      );
    },
  };
})(Drupal);
