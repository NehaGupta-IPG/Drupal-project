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
   * Full.
   * `.table-filter-text[data-full]`
   *
   * Min length of search query.
   * `.table-filter-text[data-min-length]`
   *
   * Search only from start of words.
   * `.table-filter-text[data-search-start]`
   *
   * Debug.
   * `.table-filter-text[data-debug]`
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior for the text filtering.
   */
  Drupal.behaviors.drupalFilterByText = {
    activateFilter(e) {
      const input = e.target;
      const {
        table,
        items,
        targets,
        singular,
        plural,
        full,
        minLength = 2,
        searchStart = false,
        debug = false,
      } = input.dataset;

      const ALL_PHRASE = Drupal.t('All available items are listed.');
      const SINGULAR_PHRASE = '1 item is available in the modified list.';
      const PLURAL_PHRASE = '@count items are available in the modified list.';

      const makeAnnounce = (matches) => {
        Drupal.announce(
          Drupal.formatPlural(
            matches,
            singular || SINGULAR_PHRASE,
            plural || PLURAL_PHRASE,
          ),
        );
      };

      function normalizeString(str) {
        return str
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '')
          .toLowerCase();
      }

      const reStartsWith = (query) => new RegExp(`\\b${query}`);

      function searchMethod(target, query) {
        return searchStart === 'true'
          ? target.search(reStartsWith(query)) !== -1
          : target.includes(query);
      }

      function initDebug(table) {
        table.style.outline = '2px solid red';
        table.querySelectorAll(items)?.forEach((item) => {
          item.style.backgroundColor = 'yellow';
          if (targets) {
            item.querySelectorAll(targets)?.forEach((target) => {
              target.style.outline = 'green 2px dotted';
            });
          }
        });
      }

      // Table can be in another context so we have to search in document.
      const tables = document.querySelectorAll(table);

      const initTable = (tableElement) => {
        // Transitional information helping accelerate queries.
        let filterItemsTransitional = null;
        let queryTransitional = '';

        // Prepare items for accelerated filtering.
        const filterItems = Array.from(
          tableElement.querySelectorAll(items),
        ).map((source) => {
          // If we need to search deeper in row elements.
          const textContent = targets
            ? Array.from(source.querySelectorAll(targets))
                .reduce((acc, target) => {
                  return `${acc} ${target.textContent}`;
                }, ' ')
                .trim()
            : source.textContent;

          return {
            node: source,
            textContent: normalizeString(textContent),
          };
        });

        filterItemsTransitional = filterItems;

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
          const query = normalizeString(e.target.value);
          const queryContinuation = query.startsWith(queryTransitional);

          // Return earlier and save resources.
          if (queryContinuation && filterItemsTransitional.length === 0) return;

          // Test if the updated input text is a continuation of the previous query.
          if (!searchMethod(query, queryTransitional)) {
            filterItemsTransitional = filterItems;
          }

          // Filter if the length of the query is at least 2 characters.
          if (query.length >= minLength) {
            const matches = [];

            filterItemsTransitional.forEach((item) => {
              if (!searchMethod(item.textContent, query)) {
                hideElement(item.node);
              } else {
                showElement(item.node);
                matches.push(item);
              }
            });
            makeAnnounce(matches.length);
            checkLabels();
            filterItemsTransitional = matches;
          } else {
            filterItems.forEach((item) => {
              showElement(item.node);
            });
            Drupal.announce(full || ALL_PHRASE);
            checkLabels(true);
            filterItemsTransitional = filterItems;
          }

          // Updates the transitional information.
          queryTransitional = query;
        };

        tableElement.addEventListener(FILTER_EVENT, (e) => {
          return filterTableList(e.detail.event);
        });

        if (debug) {
          initDebug(tableElement);
        }
      };

      tables.forEach(initTable);

      e.target.addEventListener(
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

      e.target.addEventListener('keydown', (e) => {
        if (e.which === 13) {
          e.preventDefault();
          e.stopPropagation();
        }
      });
    },

    attach(context) {
      once('drupal-filter-text', '.table-filter-text', context).forEach(
        (input) => {
          // Activate the input filter only when focused.
          input.addEventListener('focus', this.activateFilter, { once: true });
        },
      );
    },
  };
})(Drupal, Drupal.debounce, once);
