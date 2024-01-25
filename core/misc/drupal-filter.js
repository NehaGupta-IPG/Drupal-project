/**
 * @file
 * Common admin filter by text behaviors.
 */

(function (Drupal, debounce, once) {
  /**
   * Filters the table by a text input search string.
   *
   * The text input will have the selector.
   * `.table-filter-text`.
   *
   * Selector for search table.
   * `.table-filter-text[data-table]`
   *
   * Selectors for search items.
   * `.table-filter-text[data-items]`
   *
   * Selectors for search targets.
   * `.table-filter-text[data-targets]`
   *
   * Singular.
   * `.table-filter-text[data-singular]`
   *
   * Plural.
   * `.table-filter-text[data-plural]`
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior for the text filtering.
   */
  Drupal.behaviors.drupalFilterByText = {
    attach(context, settings) {
      const hideElement = (element) => {
        element.classList.add('hidden');
        element.setAttribute('hidden', true);
        element.style.display = 'none';
      };

      const showElement = (element) => {
        element.classList.remove('hidden');
        element.removeAttribute('hidden');
        element.style.display = 'revert';
      };

      const searchMethod = (target, query) =>
        target.textContent.toLowerCase().includes(query);

      const foundInItem = (query, item) => {
        let found = false;
        if (item.searchTargets) {
          item.searchTargets.forEach((target) => {
            if (searchMethod(target, query)) {
              found = true;
            }
          });
        } else {
          found = searchMethod(item, query);
        }
        return found;
      };

      once('drupal-filter-text', '.table-filter-text', context).forEach(
        (input) => {
          const { table, items, targets, singular, plural } = input.dataset;

          const SINGULAR_PHRASE = `1 ${
            singular || 'item'
          } is available in the modified list.`;
          const PLURAL_PHRASE = `@count ${
            plural || 'items'
          } are available in the modified list.`;

          const makeAnnounce = (matches) => {
            Drupal.announce(
              Drupal.formatPlural(
                matches.length - 1,
                SINGULAR_PHRASE,
                PLURAL_PHRASE,
              ),
            );
          };

          // Table can be in another context so we have to search in document.
          // Can be 2 tables on views page.
          once('drupal-filter-text', table, document).forEach(
            (tableElement) => {
              if (items) {
                const filterItems = tableElement.querySelectorAll(items);

                // If we need to search deeper in row elements.
                if (targets) {
                  // To avoid search in item on every query let's store them in item object.
                  filterItems.forEach((item) => {
                    item.searchTargets = item.querySelectorAll(targets);
                  });
                }

                const labels = tableElement.querySelectorAll(
                  '[data-filter-label]',
                );
                labels.forEach((label) => {
                  label.labelledItems = tableElement.querySelectorAll(
                    `[data-filter-labelledby="${label.dataset.filterLabel}"]`,
                  );
                });

                const checkLabels = (reset = false) => {
                  labels.forEach((label) => {
                    const hasVisible = Array.from(label.labelledItems).some(
                      (element) => {
                        return !element.hasAttribute('hidden');
                      },
                    );
                    if (hasVisible) {
                      if (label.nodeName === 'DETAILS') {
                        // Found in closed details.
                        if (!reset && !label.hasAttribute('open')) {
                          label.setAttribute('open', true);
                          label.setAttribute('opened-by-filter', true);
                        } else if (
                          reset &&
                          label.hasAttribute('opened-by-filter')
                        ) {
                          label.removeAttribute('open');
                          label.removeAttribute('opened-by-filter');
                        }
                      }
                      showElement(label);
                    } else {
                      hideElement(label);
                    }
                  });
                };

                const filterTableList = (e) => {
                  const query = e.target.value.toLowerCase();

                  // Filter if the length of the query is at least 2 characters.
                  if (query.length >= 2) {
                    let matches = 0;

                    filterItems.forEach((item) => {
                      if (!foundInItem(query, item)) {
                        hideElement(item);
                      } else {
                        showElement(item);
                        matches++;
                      }
                    });

                    makeAnnounce(matches);
                    checkLabels();
                  } else {
                    Drupal.announce(
                      `All available ${plural || 'items'} are listed.`,
                    );
                    filterItems.forEach((item) => {
                      showElement(item);
                    });
                    checkLabels(true);
                  }
                };

                input.addEventListener('input', debounce(filterTableList, 200));

                input.addEventListener('keydown', (e) => {
                  if (e.which === 13) {
                    e.preventDefault();
                    e.stopPropagation();
                  }
                });
              }
            },
          );
        },
      );
    },
  };
})(Drupal, Drupal.debounce, once);
