/**
 * @file
 * Common admin filter by text behaviors.
 */

(function (Drupal, debounce, once) {
  const FILTER_EVENT = 'drupal-filter-event';

  /**
   * Reusable filter function.
   *
   * @param {object} options
   *   - inputElement: The input element to bind events to.
   *   - tableSelector: Selector for the table(s) to filter.
   *   - rowSelector: Selector for the items/rows to filter.
   *   - targetSelector: (Optional) Selector for sub-elements to search within each row.
   *   - singularLabel: (Optional) Singular label for ARIA/live region.
   *   - pluralLabel: (Optional) Plural label for ARIA/live region.
   *   - fullLabel: (Optional) Label for "all items listed".
   *   - minQueryLength: (Optional) Minimum query length to trigger filtering (default: 2).
   *   - searchFromWordStart: (Optional) If true, only match from start of words.
   *   - debug: (Optional) If true, enable debug outlines.
   */
  Drupal.filterByText = function filterByText({
    inputElement,
    tableSelector,
    rowSelector,
    targetSelector = null,
    singularLabel = null,
    pluralLabel = null,
    fullLabel = null,
    minQueryLength = 2,
    searchFromWordStart = false,
    debug = false,
  }) {
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

    function normalizeString(str) {
      return str
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
    }

    function reStartsWith(query) {
      return new RegExp(`\\b${query}`);
    }

    function searchMethod(target, query) {
      return searchFromWordStart
        ? target.search(reStartsWith(query)) !== -1
        : target.includes(query);
    }

    function initDebug(table) {
      table.style.outline = '2px solid red';
      table.querySelectorAll(rowSelector)?.forEach((item) => {
        item.style.backgroundColor = 'yellow';
        if (targetSelector) {
          item.querySelectorAll(targetSelector)?.forEach((target) => {
            target.style.outline = 'green 2px dotted';
          });
        }
      });
    }

    console.warn(`I am now inside this function.`);

    const ALL_PHRASE = Drupal.t('All available items are listed.');
    const SINGULAR_PHRASE = '1 item is available in the modified list.';
    const PLURAL_PHRASE = '@count items are available in the modified list.';

    const makeAnnounce = (matches) => {
      Drupal.announce(
        Drupal.formatPlural(
          matches,
          singularLabel || SINGULAR_PHRASE,
          pluralLabel || PLURAL_PHRASE,
        ),
      );
    };

    // Table(s) can be in another context so we have to search in document.
    const tables = document.querySelectorAll(tableSelector);

    const initTable = (tableElement) => {
      let filterItemsTransitional = null;
      let queryTransitional = '';

      const filterItems = Array.from(
        tableElement.querySelectorAll(rowSelector),
      ).map((source) => {
        const textContent = targetSelector
          ? Array.from(source.querySelectorAll(targetSelector))
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

        if (queryContinuation && filterItemsTransitional.length === 0) return;

        if (!searchMethod(query, queryTransitional)) {
          filterItemsTransitional = filterItems;
        }

        if (query.length >= minQueryLength) {
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
          Drupal.announce(fullLabel || ALL_PHRASE);
          checkLabels(true);
          filterItemsTransitional = filterItems;
        }

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

    inputElement.addEventListener(
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

    inputElement.addEventListener('keydown', (e) => {
      if (e.which === 13) {
        e.preventDefault();
        e.stopPropagation();
      }
    });
  };

  // --- Behavior: Extracts data- attributes and calls the library ---
  Drupal.behaviors.drupalFilterByText = {
    attach(context) {
      console.warn(`this block has been triggered.`);
      once('drupal-filter-text', '.table-filter-text[data-auto-activate="true"]', context).forEach(
        (input) => {
          input.addEventListener(
            'focus',
            function handler() {
              // Extract and cast data- attributes.
              Drupal.filterByText({
                inputElement: input,
                tableSelector: input.dataset.table,
                rowSelector: input.dataset.items,
                targetSelector: input.dataset.targets || null,
                singularLabel: input.dataset.singular || null,
                pluralLabel: input.dataset.plural || null,
                fullLabel: input.dataset.full || null,
                minQueryLength: input.dataset.minLength,
                searchFromWordStart: input.dataset.searchStart === 'true',
                debug: input.dataset.debug === 'true',
              });
            },
            { once: true },
          );
        },
      );
    },
  };
})(Drupal, Drupal.debounce, once);
