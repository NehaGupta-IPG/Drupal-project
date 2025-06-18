/**
 * @file
 * ListFilter class for filtering admin tables and lists in Drupal.
 */

(function (Drupal) {
  /**
   * ListFilter provides filtering functionality for tables and lists.
   *
   * @class
   * @classdesc
   *   Encapsulates logic for filtering DOM elements (such as table rows or list items)
   *   based on user input, with support for accessibility announcements and group labels.
   *   Allows customization of filtering, normalization, and DOM update methods.
   *
   * @example
   *   const filter = new Drupal.ListFilter({
   *     input: document.querySelector('#my-input'),
   *     table: '#my-table',
   *     items: 'tr',
   *     singular: 'item found',
   *     plural: 'items found',
   *   });
   *   filter.filter({ ... });
   */
  class ListFilter {
    /**
     * The custom event name for triggering filtering.
     * @type {string}
     * @static
     * @readonly
     */
    static FILTER_EVENT = 'drupal-filter-event';

    /**
     * Default options for the ListFilter.
     * @type {object}
     * @static
     * @readonly
     */
    static defaultOptions = {
      minLength: 2,
      searchStart: false,
    };

    /**
     * Constructs a ListFilter instance.
     *
     * @param {object} [options={}]
     *   Configuration options and method overrides.
     *   @param {HTMLElement} [options.input]
     *     The input element to bind events to.
     *   @param {string} [options.containerSelector]
     *     Selector for the container(s) (table, list, etc.) to filter.
     *   @param {string} [options.itemsSelector]
     *     Selector for the items/rows to filter.
     *   @param {string} [options.targetsSelector]
     *     (Optional) Selector for sub-elements to search within each item.
     *   @param {string} [options.singular]
     *     (Optional) Singular label for ARIA/live region.
     *   @param {string} [options.plural]
     *     (Optional) Plural label for ARIA/live region.
     *   @param {string} [options.full]
     *     (Optional) Label for "all items listed".
     *   @param {number} [options.minLength]
     *     (Optional) Minimum query length to trigger filtering.
     *   @param {boolean} [options.searchStart]
     *     (Optional) If true, only match from start of words.
     *   @param {function} [options.hideElement]
     *     (Optional) Custom function to hide an element.
     *   @param {function} [options.showElement]
     *     (Optional) Custom function to show an element.
     *   @param {function} [options.normalizeString]
     *     (Optional) Custom function to normalize strings for searching.
     *   @param {function} [options.searchMethod]
     *     (Optional) Custom function to determine if an item matches the query.
     *   @param {function} [options.announce]
     *     (Optional) Custom function for accessibility announcements.
     *   @param {function} [options.filterItemsBuilder]
     *     (Optional) Custom function to build filterable items.
     */
    constructor(options = {}) {
      this.#applyOptions(options);
    }

    /**
     * Centralized method to apply/merge options.
     * Called in both constructor and filter().
     * @param {object} [options={}]
     */
    #applyOptions(options = {}) {
      // Merge defaults, existing instance options, and new options
      this.options = {
        ...ListFilter.defaultOptions,
        ...(this.options || {}),
        ...options,
      };

      // Assign methods, allowing overrides
      this.hideElement = this.options.hideElement || this.#hideElementImpl;
      this.showElement = this.options.showElement || this.#showElementImpl;
      this.normalizeString = this.options.normalizeString || this.#normalizeStringImpl;
      this.searchMethod = this.options.searchMethod || this.#searchMethodImpl;
      this.announce = this.options.announce || this.#announceImpl;
      this.filterItemsBuilder = this.options.filterItemsBuilder || this.#filterItemsBuilderImpl;
    }

    /**
     * Hides an element visually and semantically.
     *
     * @param {HTMLElement} element
     *   The element to hide.
     * @returns {HTMLElement}
     *   The hidden element.
     */
    #hideElementImpl(element) {
      element.style.display = 'none';
      return element;
    }

    /**
     * Shows an element visually and semantically.
     *
     * @param {HTMLElement} element
     *   The element to show.
     * @returns {HTMLElement}
     *   The shown element.
     */
    #showElementImpl(element) {
      element.style.display = '';
      return element;
    }

    /**
     * Normalizes a string for case-insensitive and accent-insensitive comparison.
     *
     * @param {string} str
     *   The string to normalize.
     * @returns {string}
     *   The normalized string.
     */
    #normalizeStringImpl(str) {
      return window.slugify(str, { separator: ' ' });
    }

    /**
     * Returns a RegExp to match the start of a word.
     *
     * @param {string} query
     *   The query string.
     * @returns {RegExp}
     *   The regular expression.
     */
    #regexpStartsWith(query) {
      return new RegExp(`\\b${query}`);
    }

    /**
     * Determines if a target string matches the query.
     *
     * @param {string} target
     *   The string to search in.
     * @param {string} query
     *   The search query.
     * @param {boolean} searchFromWordStart
     *   Whether to match only from the start of words.
     * @returns {boolean}
     *   True if the target matches the query.
     */
    #searchMethodImpl(target, query, searchFromWordStart) {
      return searchFromWordStart
        ? target.search(this.#regexpStartsWith(query)) !== -1
        : target.includes(query);
    }

    /**
     * Announces the number of matches for accessibility.
     *
     * @param {number} matches
     *   The number of matches.
     * @param {string} singularLabel
     *   Singular label for ARIA/live region.
     * @param {string} pluralLabel
     *   Plural label for ARIA/live region.
     */
    #announceImpl(matches, singularLabel, pluralLabel) {
      Drupal.announce(Drupal.formatPlural(matches, singularLabel, pluralLabel));
    }

    /**
     * Builds the filter items from the table element.
     *
     * @param {HTMLElement} tableElement
     *   The table element to filter.
     * @param {string} rowSelector
     *   The selector for the rows/items to filter.
     * @param {string|null} targetSelector
     *   The selector for sub-elements to search within each row.
     * @param {function} normalizeString
     *   A function to normalize the text content of each item.
     * @returns {Array}
     *   An array of objects containing the node and normalized text content.
     */
    #filterItemsBuilderImpl(tableElement, rowSelector, targetSelector, normalizeString) {
      return Array.from(tableElement.querySelectorAll(rowSelector)).map((source) => {
        const textContent = targetSelector
          ? Array.from(source.querySelectorAll(targetSelector))
              .map((t) => t.textContent)
              .join(' ')
          : source.textContent;
        return {
          node: source,
          textContent: normalizeString(textContent),
        };
      });
    }

    /**
     * 
     * 
     * @param {*} label 
     * @param {boolean} reset 
     * @returns 
     */
    #labelCollapse(label, reset = false) {
      if (label.nodeName !== 'DETAILS') {
        return;
      }

      if (!reset && !label.open) {
        label.open = true;
        label.dataset.openedByFilter = true;
      } else if (reset && label.dataset.openedByFilter) {
        label.open = false;
        delete label.dataset.openedByFilter;
      }
    }

    /**
     * Filters the table/list based on the input event.
     *
     * @param {Event} e
     *   The input event.
     * @param {Array} allItems
     *   The original filter items.
     * @param {NodeList} labels
     *   The label elements.
     * @param {object} options
     *   Options for filtering.
     */
    filterTableList(e, allItems, labels, options) {
      const { singular, plural, full, minLength, searchStart } = options;
      const query = this.normalizeString(e.detail.event.target.value);
      const toShow = [];
      const toHide = [];
      const visibleGroups = {};

      if (query.length >= minLength) {
        allItems.forEach((item) => {
          if (this.searchMethod(item.textContent, query, searchStart)) {
            toShow.push(item.node);
            const group = item.node.dataset.filterParentGroup;
            if (group) {
              visibleGroups[group] = true;
            }
          } else {
            toHide.push(item.node);
          }
        });

        requestAnimationFrame(() => {
          toShow.forEach((node) => this.showElement(node));
          toHide.forEach((node) => this.hideElement(node));
          labels.forEach((label) => {
            if (visibleGroups[label.dataset.filterGroupName]) {
              this.showElement(label);
            } else {
              this.hideElement(label);
            }
            this.#labelCollapse(label);
          });
          this.announce(toShow.length, singular, plural);
        });
      } else {
        allItems.forEach((item) => {
          toShow.push(item.node);
        });
        requestAnimationFrame(() => {
          toShow.forEach((node) => this.showElement(node));
          labels.forEach((label) => this.showElement(label));
          Drupal.announce(full ?? Drupal.t('All available items are listed.'));
          labels.forEach((label) => {
            this.#labelCollapse(label, true);
          });
        });
      }

      // Expose an event for other components to listen to.
      if (this.options.input) {
        const event = new CustomEvent('searchingFinished', {
          detail: { matches: toShow }
        });
        this.options.input.dispatchEvent(event);
      }
    }

    /**
     * Initializes filtering on the specified input and table(s).
     *
     * @param {object} options
     *   Configuration options for filtering.
     * @returns {ListFilter}
     *   The ListFilter instance (for chaining).
     */
    filter(options) {
      this.#applyOptions(options);

      const {
        input,
        containerSelector,
        itemsSelector,
        targetsSelector = null,
        singular = null,
        plural = null,
        full = null,
        minLength = this.options.minLength,
        searchStart = this.options.searchStart,
      } = this.options;

      const containers = document.querySelectorAll(containerSelector);

      containers.forEach((containerElement) => {
        const filterItems = this.filterItemsBuilder(
          containerElement,
          itemsSelector,
          targetsSelector,
          this.normalizeString,
        );

        const labels = containerElement.querySelectorAll('[data-filter-group-name]');

        // Bind the input event to the container element.
        containerElement.addEventListener(ListFilter.FILTER_EVENT, (e) => {
          this.filterTableList(e, filterItems, labels, {
            singular,
            plural,
            full,
            minLength,
            searchStart,
          });
        });
      });

      input.addEventListener(
        'input',
        Drupal.debounce((event) => {
          containers.forEach((containerElement) => {
            containerElement.dispatchEvent(
              new CustomEvent(ListFilter.FILTER_EVENT, { detail: { event } }),
            );
          });
        }, 200),
      );

      input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          e.stopPropagation();
        }
      });

      return this;
    }
  }

  // Expose the class to the outside world:
  Drupal.ListFilter = ListFilter;

})(Drupal);
