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
   *   // Attach your event listeners BEFORE instantiating ListFilter.
   * 
   *   // Override default options by listening to 'listFilter:options':
   *   const input = document.querySelector('#my-input');
   *   input.addEventListener('listFilter:options', (event) => {
   *     event.detail.options.minLength = 5;
   *     event.detail.options.hideElement = (el) => {
   *       el.style.display = 'none'; return el;
   *     };
   *   });
   *
   *   // search results will be announced using the 'listFilter:searchingFinished' event:
   *   input.addEventListener('listFilter:searchingFinished', (event) =>{
   *     const { matched, unmatched } = event.detail;
   *     Drupal.announce(
   *       Drupal.formatPlural(
   *         matched.length,
   *         '1 item found',
   *         '@matched items found; @unmatched items not found',
   *         {
   *           '@matched': matched.length,
   *           '@unmatched': unmatched.length,
   *         }
   *       )
   *     );
   *   });
   *
   *   // Then instantiate ListFilter:
   *   const filter = new Drupal.ListFilter({
   *     input: document.querySelector('#my-input'),
   *     containerSelector: '#my-table',
   *     itemsSelector: 'tr',
   *     singular: 'item found',
   *     plural: 'items found',
   *   });
   *   filter.filter();
   *
   */
  class ListFilter {
    /**
     * The custom event name for triggering filtering.
     * @type {string}
     * @static
     * @readonly
     */
    static FILTER_EVENT = 'listFilter:filter';

    /**
     * The custom event name for overriding options.
     * @type {string}
     * @static
     * @readonly
     */
    static OPTIONS_EVENT = 'listFilter:options';

    /**
     * The custom event name for signaling that searching has finished.
     * @type {string}
     * @static
     * @readonly
     */
    static SEARCH_FINISHED_EVENT = 'listFilter:searchingFinished';

    /**
     * Default options for the ListFilter.
     * @type {object}
     * @static
     * @readonly
     */
    static defaultOptions = {
      minLength: 2,
      searchStart: false,
      singular: null,
      plural: null,
      full: null,
      containerSelector: null,
      itemsSelector: null,
      targetsSelector: null,
      hideElement: null,
      showElement: null,
      normalizeString: null,
      searchMethod: null,
      announce: null,
      filterItemsBuilder: null,
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
      if (!this.#validOptions(options)) {
        throw new Error(Drupal.t('ListFilter: The options provided are not vaid.'));
      }

      this.input = options.input;
      const merged = {
        ...ListFilter.defaultOptions,
        ...options,
      };

      // Dispatch event to allow external modules to override options
      const event = new CustomEvent(ListFilter.OPTIONS_EVENT, {
        detail: { options: merged },
        bubbles: false,
        cancelable: true,
      });
      this.input.dispatchEvent(event);

      // Use the (possibly modified) options from event.detail.options
      const consolidateOptions = this.options = {
        ...merged,
        ...event.detail?.options || {},
      };

      // Assignment
      this.containerSelector = consolidateOptions.containerSelector;
      this.itemsSelector = consolidateOptions.itemsSelector;
      this.targetsSelector = consolidateOptions.targetsSelector;
      this.singular = consolidateOptions.singular;
      this.plural = consolidateOptions.plural;
      this.full = consolidateOptions.full;
      this.minLength = consolidateOptions.minLength;
      this.searchStart = consolidateOptions.searchStart;
      this.hideElement = consolidateOptions.hideElement || this.#hideElementImpl;
      this.showElement = consolidateOptions.showElement || this.#showElementImpl;
      this.normalizeString = consolidateOptions.normalizeString || this.#normalizeStringImpl;
      this.searchMethod = consolidateOptions.searchMethod || this.#searchMethodImpl;
      this.announce = consolidateOptions.announce || this.#announceImpl;
      this.filterItemsBuilder = consolidateOptions.filterItemsBuilder || this.#filterItemsBuilderImpl;
    }

    /**
     * Initializes filtering on the specified input and container(s).
     */
    filter() {
      const containers = document.querySelectorAll(this.containerSelector);
      containers.forEach((container) => {
        const filterItems = this.filterItemsBuilder(
          container,
          this.itemsSelector,
          this.targetsSelector,
          this.normalizeString,
        );

        const labels = container.querySelectorAll('[data-filter-group-name]');

        // Bind the input event to the container.
        container.addEventListener(ListFilter.FILTER_EVENT, (event) => {
          this.filterTableList(event, filterItems, labels);
        });
      });

      this.input.addEventListener(
        'input',
        Drupal.debounce((event) => {
          containers.forEach((container) => {
            container.dispatchEvent(
              new CustomEvent(ListFilter.FILTER_EVENT, { detail: { event } }),
            );
          });
        }, 200),
      );

      this.input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          event.stopPropagation();
        }
      });

      return this;
    }

    /**
     * Filters the table/list based on the input event.
     *
     * @param {Event} event
     *   The input event.
     * @param {Array} allItems
     *   The original filter items.
     * @param {NodeList} labels
     *   The label elements.
     */
    filterTableList(event, allItems, labels) {
      const query = this.normalizeString(event.detail.event.target.value);
      const toShow = [];
      const toHide = [];
      const visibleGroups = {};

      if (query.length >= this.minLength) {
        allItems.forEach((item) => {
          if (this.searchMethod(item.textContent, query, this.searchStart)) {
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
            this.#labelCollapse(label, false);
          });
          this.announce(toShow.length, this.singular, this.plural);
        });
      } else {
        allItems.forEach((item) => {
          toShow.push(item.node);
        });
        requestAnimationFrame(() => {
          toShow.forEach((node) => this.showElement(node));
          labels.forEach((label) => this.showElement(label));
          Drupal.announce(this.full ?? Drupal.t('All available items are listed.'));
          labels.forEach((label) => {
            this.#labelCollapse(label, true);
          });
        });
      }

      // Dispatch a custom event to signal that searching has finished.
      requestAnimationFrame(() => {
        const event = new CustomEvent(ListFilter.SEARCH_FINISHED_EVENT, {
          detail: {
            matched: toShow,
            unmatched: toHide,
          }
        });
        this.input.dispatchEvent(event);
      });
    }

    /**
     * Assigns all option values to class properties.
     * @param {object} opts
     *   The options object to assign.
     */
    #assignOptions(options) {

    }

    /**
     * Ensure options is a vaid object.
     * 
     * @param {*} options 
     * @returns boolean
     *   Return true if options is a valid object.
     */
    #validOptions(options) {
      return (
        options &&
        typeof options === 'object' &&
        options.input instanceof HTMLElement &&
        typeof options.containerSelector === 'string' &&
        typeof options.itemsSelector === 'string'
      );
    }

    /**
     * Filters the options to only include valid keys.
     * 
     * @param  {...options} options 
     * @returns 
     */
    #filterValidKeys(...options) {
      const aggregated = Object.assign({}, ...options);
      const validKeys = Object.keys(ListFilter.defaultOptions);
      return Object.fromEntries(
        Object.entries(aggregated).filter(([key]) => validKeys.includes(key))
      );
    }

    /**
     * Hides an element visually and semantically.
     * @param {HTMLElement} element
     * @returns {HTMLElement}
     */
    #hideElementImpl(element) {
      element.style.display = 'none';
      return element;
    }

    /**
     * Shows an element visually and semantically.
     * @param {HTMLElement} element
     * @returns {HTMLElement}
     */
    #showElementImpl(element) {
      element.style.display = '';
      return element;
    }

    /**
     * Normalizes a string for case-insensitive and accent-insensitive comparison.
     * @param {string} str
     * @returns {string}
     */
    #normalizeStringImpl(str) {
      return window.slugify(str, { separator: ' ' });
    }

    /**
     * Returns a RegExp to match the start of a word.
     * @param {string} query
     * @returns {RegExp}
     */
    #regexpStartsWith(query) {
      return new RegExp(`\\b${query}`);
    }

    /**
     * Determines if a target string matches the query.
     * @param {string} target
     * @param {string} query
     * @param {boolean} searchFromWordStart
     * @returns {boolean}
     */
    #searchMethodImpl(target, query, searchFromWordStart) {
      return searchFromWordStart
        ? target.search(this.#regexpStartsWith(query)) !== -1
        : target.includes(query);
    }

    /**
     * Announces the number of matches for accessibility.
     * @param {number} matches
     * @param {string} singularLabel
     * @param {string} pluralLabel
     */
    #announceImpl(matches, singularLabel, pluralLabel) {
      Drupal.announce(Drupal.formatPlural(matches, singularLabel, pluralLabel));
    }

    /**
     * Builds the filter items from the container element.
     * @param {HTMLElement} containerElement
     * @param {string} itemsSelector
     * @param {string|null} targetSelector
     * @param {function} normalizeString
     * @returns {Array}
     */
    #filterItemsBuilderImpl(containerElement, itemsSelector, targetSelector, normalizeString) {
      return Array.from(containerElement.querySelectorAll(itemsSelector)).map((source) => {
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
     * Expands or collapses a <details> label as needed.
     * @param {*} label
     * @param {boolean} reset
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
  }

  // Expose the class to Drupal namespace
  Drupal.ListFilter = ListFilter;
})(Drupal);
