/**
 * @file
 * Common admin filter by text behaviors.
 */

(function (Drupal, debounce, once) {
  const FILTER_EVENT = 'drupal-filter-event';

  function hideElement(element) {
    element.classList.add('hidden');
    element.setAttribute('hidden', true);
    element.style.display = 'none';
  }

  function showElement(element) {
    element.classList.remove('hidden');
    element.removeAttribute('hidden');
    element.style.display = 'revert';
  }

  function searchMethod(target, query) {
    return target?.textContent.toLowerCase().includes(query) || false;
  }

  function foundInItem(query, item) {
    if (item.searchTargets) {
      return Array.from(item.searchTargets).some((target) =>
        searchMethod(target, query),
      );
    }
    return searchMethod(item, query);
  }

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
      once('drupal-filter-text', '.table-filter-text', context).forEach(
        (input) => {
          const { table, items, targets, singular, plural, full } =
            input.dataset;

          const ALL_PHRASE = Drupal.t('All available items are listed.');
          const SINGULAR_PHRASE = '1 item is available in the modified list.';
          const PLURAL_PHRASE =
            '@count items are available in the modified list.';

          const makeAnnounce = (matches) => {
            Drupal.announce(
              Drupal.formatPlural(
                matches,
                singular || SINGULAR_PHRASE,
                plural || PLURAL_PHRASE,
              ),
            );
          };

          // Table can be in another context so we have to search in document.
          const tables = document.querySelectorAll(table);

          const initTable = (tableElement) => {
            const filterItems = tableElement.querySelectorAll(items);

            // If we need to search deeper in row elements.
            if (targets) {
              // To avoid search in item on every query let's store them in item object.
              filterItems.forEach((item) => {
                item.searchTargets = item.querySelectorAll(targets);
              });
            }
            const labels = tableElement.querySelectorAll('[data-filter-label]');

            labels.forEach((label) => {
              label.labelledItems = tableElement.querySelectorAll(
                `[data-filter-labelledby="${label.dataset.filterLabel}"]`,
              );
            });

            const checkLabels = (reset = false) => {
              labels.forEach((label) => {
                if (
                  Array.from(label.labelledItems).some(
                    (element) => !element.hasAttribute('hidden'),
                  )
                ) {
                  if (label.nodeName === 'DETAILS') {
                    if (!reset && !label.open) {
                      label.open = true;
                      label.dataset.openedByFilter = true;
                    } else if (reset && label.dataset.openedByFilter) {
                      label.open = false;
                      delete label.dataset.openedByFilter;
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
                    matches += 1;
                  }
                });
                makeAnnounce(matches);
                checkLabels();
              } else {
                filterItems.forEach(showElement);
                Drupal.announce(full || ALL_PHRASE);
                checkLabels(true);
              }
            };

            tableElement.addEventListener(FILTER_EVENT, (e) =>
              filterTableList(e.detail.event),
            );
          };

          tables.forEach(initTable);

          input.addEventListener(
            'input',
            debounce((event) => {
              tables.forEach((tableElement) => {
                tableElement.dispatchEvent(
                  new CustomEvent(FILTER_EVENT, {
                    detail: {
                      event,
                    },
                  }),
                );
              });
            }, 200),
          );

          input.addEventListener('keydown', (e) => {
            if (e.which === 13) {
              e.preventDefault();
              e.stopPropagation();
            }
          });
        },
      );
    },
  };
})(Drupal, Drupal.debounce, once);
