/**
 * @file
 * test_filter_library behaviors.
 */
(function (Drupal) {
  Drupal.behaviors.listFilterOverride = {
    attach(context) {
      const result = document.querySelector('.js-filter-count');
      once(
        'list-filter-override',
        '.table-filter-text[data-auto-activate="true"]',
        context,
      ).forEach((input) => {
        input.addEventListener('listFilter:searchingFinished', (e) => {
          result.classList.remove('hidden');
          result.innerText = `Results found: ${e.detail.matched.length}`;
        });
      });
    },
  };
})(Drupal);
