/**
 * @file
 * test_filter_library behaviors.
 */
(function (Drupal) {
  Drupal.behaviors.listFilterOverride = {
    attach(context) {
      let customListFilter;
      once('drupal-list-filter', '.filter-input', context).forEach((input) => {
        const {
          table,
          items,
          targets,
          singular,
          plural,
          full,
          minLength,
          searchStart,
          debug,
        } = input.dataset;

        input.addEventListener(
          'focus',
          function handler() {
            customListFilter = Drupal.listFilter.filter({
              inputElement: input,
              tableSelector: table,
              rowSelector: items,
              targetSelector: targets || null,
              singularLabel: singular || null,
              pluralLabel: plural || null,
              fullLabel: full || null,
              minQueryLength: minLength ? parseInt(minLength, 10) : undefined,
              searchFromWordStart: searchStart === 'true',
              debug: debug === 'true',
              hideElement: (e) => {
                Drupal.listFilter.hideElement(e);
                e.classList.add('element-not-matched');
                e.classList.remove('element-matched');
                return e;
              },
              showElement: (e) => {
                Drupal.listFilter.showElement(e);
                e.classList.remove('element-not-matched');
                e.classList.add('element-matched');
                return e;
              },
              searchMethod: (a, b, c) => {
                return Drupal.listFilter.searchMethod(a, b, c);
              },
            });
          },
          { once: true },
        );

        const result = document.querySelector('.js-filter-count');
        // console.log(result);
        input.addEventListener('blur', function handler() {
          result.classList.remove('hidden');
          // console.log('Input event triggered', customListFilter);
          result.innerText = 'Results found: ' + customListFilter.matchedRows;
        });
      });
    },
  };
})(Drupal);
