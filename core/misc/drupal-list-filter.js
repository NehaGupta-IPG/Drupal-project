/**
 * @file
 * List filter for admin tables and lists.
 */

(function (Drupal, debounce, once) {
  /**
   * Default phrases for filter status messages.
   *
   * @const {string} FILTER_EVENT
   *   The custom event name for triggering filtering.
   * @const {string} ALL_PHRASE
   *   Default phrase for "all items listed".
   * @const {string} SINGULAR_PHRASE
   *   Default phrase for a single item.
   * @const {string} PLURAL_PHRASE
   *   Default phrase for multiple items.
   */
  const FILTER_EVENT = 'drupal-filter-event';
  const ALL_PHRASE = Drupal.t('All available items are listed.');
  const SINGULAR_PHRASE = '1 item is available in the modified list.';
  const PLURAL_PHRASE = '@count items are available in the modified list.';

  /**
   * Default options for the list filter.
   */
  const defaultOptions = {
    minLength: 2,
    searchStart: false,
    debug: false,
  };

  /**
   * List filter object.
   */
  let listFilter;

  /**
   * Hides an element visually and semantically.
   *
   * @function Drupal.listFilter.hideElement
   * @param {HTMLElement} element
   *   The element to hide.
   */
  function defaultHideElement(element) {
    element.classList.add('hidden');
    element.setAttribute('hidden', true);
    element.style.display = 'none';
    return element;
  }

  /**
   * Shows an element visually and semantically.
   *
   * @function Drupal.listFilter.showElement
   * @param {HTMLElement} element
   *   The element to show.
   */
  function defaultShowElement(element) {
    element.classList.remove('hidden');
    element.removeAttribute('hidden');
    element.style.display = '';
    return element;
  }

  /**
   * Normalizes a string for case-insensitive and accent-insensitive comparison.
   *
   * @function Drupal.listFilter.normalizeString
   * @param {string} str
   *   The string to normalize.
   * @return {string}
   *   The normalized string.
   */
  function defaultNormalizeString(str) {
    return str
      ? str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
      : '';
  }

  /**
   * Returns a RegExp to match the start of a word.
   *
   * @function Drupal.listFilter.reStartsWith
   * @param {string} query
   *   The query string.
   * @return {RegExp}
   *   The regular expression.
   */
  function reStartsWith(query) {
    return new RegExp(`\\b${query}`);
  }

  /**
   * Determines if a target string matches the query.
   *
   * @function Drupal.listFilter.searchMethod
   * @param {string} target
   *   The string to search in.
   * @param {string} query
   *   The search query.
   * @param {boolean} searchFromWordStart
   *   Whether to match only from the start of words.
   * @return {boolean}
   *   True if the target matches the query.
   */
  function defaultSearchMethod(target, query, searchFromWordStart) {
    return searchFromWordStart
      ? target.search(reStartsWith(query)) !== -1
      : target.includes(query);
  }

  /**
   * Announces the number of matches for accessibility.
   *
   * @function Drupal.listFilter.announce
   * @param {number} matches
   *   The number of matches.
   * @param {string|null} singularLabel
   *   Singular label for ARIA/live region.
   * @param {string|null} pluralLabel
   *   Plural label for ARIA/live region.
   * @param {string} SINGULAR_PHRASE
   *   Default singular phrase.
   * @param {string} PLURAL_PHRASE
   *   Default plural phrase.
   */
  function defaultAnnounce(matches, singularLabel, pluralLabel, SINGULAR_PHRASE, PLURAL_PHRASE) {
    Drupal.announce(
      Drupal.formatPlural(
        matches,
        singularLabel ?? SINGULAR_PHRASE,
        pluralLabel ?? PLURAL_PHRASE,
      ),
    );
  }

  /**
   * Builds the filter items from the table element.
   *
   * @function Drupal.listFilter.filterItemsBuilder
   * @param {HTMLElement} tableElement
   *   The table element to filter.
   * @param {string} rowSelector
   *   The selector for the rows/items to filter.
   * @param {string|null} targetSelector
   *   The selector for sub-elements to search within each row.
   * @param {function} normalizeString
   *   A function to normalize the text content of each item.
   *
   * @return {Array}
   * An array of objects containing the node and normalized text content.
   *
   */
  function defaultFilterItemsBuilder(tableElement, rowSelector, targetSelector, normalizeString) {
    return Array.from(tableElement.querySelectorAll(rowSelector)).map((source) => {
      const textContent = targetSelector
        ? Array.from(source.querySelectorAll(targetSelector)).map(t => t.textContent).join(' ')
        : source.textContent;
      return {
        node: source,
        textContent: normalizeString(textContent),
      };
    });
  }

  /**
   * Default function to check and update the visibility of labels.
   * 
   * @function Drupal.listFilter.defaultCheckLabels
   * @param {Array} labels
   *   An array of label elements to check.
   * @param {boolean} reset
   *   Whether to reset the label state.
   */
  function defaultCheckLabels(labels, reset = false) {
    for (const label of labels) {
      const anyVisible = Array.from(label.labelledItems).some(
        (element) => !element.hasAttribute('hidden'),
      );
      if (!anyVisible) {
        this.hideElement(label);
        continue;
      }
      // @todo make the function to process other node types overridable, for example, fieldset elements.
      // we can check if the function exists then call it, or we can use a different approach.
      // like this[checkFIELDSETLabels](reset, label);
      if (label.nodeName === 'DETAILS') {
        this.checkDetailedLabels(reset, label);
      }
      this.showElement(label);
    }
  }

  /**
   * Default function to check and update the opened status of detailed labels.
   * 
   * @param {boolean} reset
   *   Whether to reset the label state.
   * @param {string} label
   *   The label element to check.
   */
  function defaultCheckDetailedLabels(reset, label) {
    if (!reset && !label.open) {
      label.open = true;
      label.dataset.openedByFilter = true;
    } else if (reset && label.dataset.openedByFilter) {
      label.open = false;
      delete label.dataset.openedByFilter;
    }
  }

  /**
   * Default debug function to visually highlight elements.
   * 
   * @function Drupal.listFilter.defaultDebug
   * @param {HTMLElement} tableElement 
   *   The table element to debug.
   * @param {string} rowSelector
   *   The selector for the rows/items to filter.
   * @param {string} targetSelector 
   *   The selector for sub-elements to search within each row.
   */
  function defaultDebug(tableElement, rowSelector, targetSelector) {
    tableElement.style.outline = '2px solid red';
    tableElement.querySelectorAll(rowSelector).forEach((item) => {
      item.style.backgroundColor = 'yellow';
      if (targetSelector) {
        item.querySelectorAll(targetSelector).forEach((target) => {
          target.style.outline = 'green 2px dotted';
        });
      }
    });
  }

  /**
   * List filter object.
   *
   * @namespace Drupal.listFilter
   */
  listFilter = {

    /**
     * Total rows matched by the filter.
     */
    matchedRows: null,

    /**
     * @todo improve this variable or how can we handle the end of searching.
     */
    searchingFinished: undefined,

    /**
     * Hides an element. Can be overridden.
     *
     * @type {function}
     */
    hideElement: defaultHideElement,

    /**
     * Shows an element. Can be overridden.
     *
     * @type {function}
     */
    showElement: defaultShowElement,

    /**
     * Normalizes a string. Can be overridden.
     *
     * @type {function}
     */
    normalizeString: defaultNormalizeString,

    /**
     * Determines if a string matches a query. Can be overridden.
     *
     * @type {function}
     */
    searchMethod: defaultSearchMethod,

    /**
     * Announces the number of matches. Can be overridden.
     *
     * @todo add this function in the override filter "constructor"
     *
     * @type {function}
     */
    announce: defaultAnnounce,

    /**
     * Checks and updates the visibility of labels.
     * 
     * @type {function}
     */
    checkLabels: defaultCheckLabels,

    /**
     * Checks and updates the visibility of labels.
     * 
     * @type {function}
     */
    checkDetailedLabels: defaultCheckDetailedLabels,

    /**
     * Default debug function. Can be overridden.
     * 
     * @type {function}
     */
    debug: defaultDebug,

    /*
     * Builds filter items from the table element. Can be overridden.
     *
     * @todo add this function in the override filter "constructor"
     *
     * @type {function}
     * @param {HTMLElement} tableElement
     *   The table element to filter.
     * @param {string} rowSelector
     *   The selector for the rows/items to filter.
     * @param {string|null} targetSelector
     *   The selector for sub-elements to search within each row.
     * @param {function} normalizeString
     *   A function to normalize the text content of each item.
     * @return {Array}
     *   An array of objects containing the node and normalized text content.
     */
    filterItemsBuilder: defaultFilterItemsBuilder,

    /**
     * Default options for the filter.
     * @todo update this instance when filter created.
     *
     * @type {object}
     */
    options: {},

    /**
     * Filters table/list rows based on input.
     *
     * @function Drupal.listFilter.filter
     * @param {object} options
     *   - inputElement: {HTMLElement} The input element to bind events to.
     *   - table: {string} Selector for the table(s) to filter.
     *   - items: {string} Selector for the items/rows to filter.
     *   - targets: {string|null} (Optional) Selector for sub-elements to search within each row.
     *   - singular: {string|null} (Optional) Singular label for ARIA/live region.
     *   - plural: {string|null} (Optional) Plural label for ARIA/live region.
     *   - full: {string|null} (Optional) Label for "all items listed".
     *   - minLength: {number} (Optional) Minimum query length to trigger filtering.
     *   - searchStart: {boolean} (Optional) If true, only match from start of words.
     *   - debug: {boolean} (Optional) If true, enable debug outlines.
     *   - hideElement: {function} (Optional) Custom hideElement override.
     *   - showElement: {function} (Optional) Custom showElement override.
     */
    filter: function (options) {
      const {
        input,
        table,
        items,
        targets = null,
        singular = null,
        plural = null,
        full = null,
        minLength = defaultOptions.minLength,
        searchStart = defaultOptions.searchStart,
        debug = defaultOptions.debug,
        hideElement = this.hideElement,
        showElement = this.showElement,
        searchMethod = this.searchMethod,
        normalizeString = this.normalizeString,
        filterItemsBuilder = this.filterItemsBuilder,
        checkLabels = this.checkLabels,
        checkDetailedLabels = this.checkDetailedLabels,
        announce = this.announce,
        debug: debugFunction = this.debug,
        searchingFinished = this.searchingFinished ?? undefined,
      } = options;

      const tables = document.querySelectorAll(table);

      for (const tableElement of tables) {
        let filterItemsTransitional = null;
        let queryTransitional = '';

        const filterItems = this.filterItemsBuilder(
          tableElement,
          items,
          targets,
          listFilter.normalizeString,
        );

        filterItemsTransitional = filterItems;

        const labels = tableElement.querySelectorAll('[data-filter-label]');
        for (const label of labels) {
          label.labelledItems = tableElement.querySelectorAll(
            `[data-filter-labelledby="${label.dataset.filterLabel}"]`,
          );
        }

        /**
         * Filters the table/list based on the input event.
         *
         * @param {Event} e
         *   The input event.
         */
        function filterTableList(e) {
          e = e.detail.event;
          tableElement.classList.remove('js-list-filtered');
          const query = this.normalizeString(e.target.value);
          const queryContinuation = query.startsWith(queryTransitional);

          if (queryContinuation && filterItemsTransitional.length === 0) return;
          if (!searchMethod(query, queryTransitional, searchStart)) {
            filterItemsTransitional = filterItems;
          }

          if (query.length >= minLength) {
            const matches = [];
            for (const item of filterItemsTransitional) {
              if (!searchMethod(item.textContent, query, searchStart)) {
                hideElement(item.node);
              } else {
                this.showElement(item.node);
                matches.push(item);
              }
            }
            this.announce(
              matches.length,
              singular,
              plural,
              SINGULAR_PHRASE,
              PLURAL_PHRASE
            );
            this.checkLabels(labels);
            filterItemsTransitional = matches;
          } else {
            for (const item of filterItems) {
              this.showElement(item.node); // Use override or default
            }
            Drupal.announce(full ?? ALL_PHRASE);
            this.checkLabels(labels, true);
            filterItemsTransitional = filterItems;
          }
          this.matchedRows = filterItemsTransitional.length;
          tableElement.classList.add('js-list-filtered');
          if (this.searchingFinished !== undefined) {
            this.searchingFinished();
          }
          queryTransitional = query;
        }

        tableElement.addEventListener(FILTER_EVENT, filterTableList.bind(this));

        if (debug) {
          this.debug(tableElement, items, targets);
        }
      }

      input.addEventListener(
        'input',
        debounce((event) => {
          tables.forEach((tableElement) => {
            tableElement.dispatchEvent(
              new CustomEvent(FILTER_EVENT, { detail: { event } }),
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
      return listFilter;
    },
  };

  /**
   * Expose the listFilter object.
   *
   * @namespace Drupal.listFilter
   */
  Drupal.listFilter = listFilter;

  /**
   * Behavior to attach the list filter to elements.
   *
   * @namespace Drupal.behaviors.drupalListFilter
   */
  Drupal.behaviors.drupalListFilter = {
    attach(context) {
      once('drupal-list-filter', '.table-filter-text[data-auto-activate="true"]', context).forEach(
        (input) => {
          input.addEventListener(
            'focus',
            function handler() {
              let options = {
                input: input,
                ...input.dataset,
              };

              const event = new CustomEvent('listFilter:options', { detail: { options, input } });
              input.dispatchEvent(event);

              Drupal.listFilter.filter(event.detail.options);
            },
            { once: true },
          );
        }
      );
    }
  };
})(Drupal, Drupal.debounce, once);
