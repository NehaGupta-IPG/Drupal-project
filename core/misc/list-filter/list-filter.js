/**
 * @file
 * ListFilter class for filtering admin tables and lists in Drupal.
 */

(function (Drupal, slugify) {
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
   *   // Override default settings by listening to 'listFilter:settings':
   *   const input = document.querySelector('#my-input');
   *   input.addEventListener('listFilter:settings', (event) => {
   *     event.detail.settings.minLength = 5;
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
   *   // Make sure to add the in the instances list.
   *   ListFilter.instances.set(input, filter);
   *
   */
  class ListFilter {
    /**
     * The custom event name for triggering filtering.
     * @type {string}
     * @static
     * @readonly
     */
    static get FILTER_EVENT() {
      return 'listFilter:filter';
    }

    /**
     * The custom event name for overriding settings.
     * @type {string}
     * @static
     * @readonly
     */
    static get SETTINGS_EVENT() {
      return 'listFilter:settings';
    }

    /**
     * The custom event name for signaling that searching has finished.
     * @type {string}
     * @static
     * @readonly
     */
    static get SEARCH_FINISHED_EVENT() {
      return 'listFilter:searchingFinished';
    }

    /**
     * Default settings for the ListFilter.
     * @type {object}
     * @static
     * @readonly
     */
    static get DEFAULT_SETTINGS() {
      return {
        minLength: 2,
        searchStartOfWords: false,
        announceSingular: null,
        announcePlural: null,
        announceFull: Drupal.t('All available items are listed.'),
        containerSelector: null,
        itemsSelector: null,
        targetsSelector: null,
      };
    }

    /**
     * Constructs a ListFilter instance.
     *
     * @param {HTMLInputElement} input
     */
    constructor(input) {
      this.input = input;
      const settings = input.dataset?.listFilter
        ? JSON.parse(input.dataset.listFilter)
        : {};
      const merged = {
        ...ListFilter.DEFAULT_SETTINGS,
        ...settings,
      };

      // Dispatch event to allow external modules to override settings
      const event = new CustomEvent(ListFilter.SETTINGS_EVENT, {
        detail: { settings: merged },
        bubbles: false,
        cancelable: true,
      });
      this.input.dispatchEvent(event);

      // Use the (possibly modified) settings from event.detail.settings
      const consolidateSettings = event.detail.settings;

      // Assignment
      this.containerSelector = consolidateSettings.containerSelector;
      this.itemsSelector = consolidateSettings.itemsSelector;
      this.targetsSelector = consolidateSettings.targetsSelector;
      this.announceSingular = consolidateSettings.announceSingular;
      this.announcePlural = consolidateSettings.announcePlural;
      this.announceFull = consolidateSettings.announceFull;
      this.minLength = consolidateSettings.minLength;
      this.searchStartOfWords = consolidateSettings.searchStartOfWords;
      this.hideElement = ListFilter._hideElementImpl;
      this.showElement = ListFilter._showElementImpl;
      this.normalizeString = ListFilter._normalizeStringImpl;
      this.searchMethod = ListFilter._searchMethodImpl;
      this.announce = ListFilter._announceImpl;
      this.filterItemsBuilder = ListFilter._filterItemsBuilderImpl;

      this._initialize();
    }

    /**
     * Initializes filtering on the specified input and container(s).
     */
    _initialize() {
      this._boundFilterTableList = this._filterTableList.bind(this);
      this.containers = Array.from(
        document.querySelectorAll(this.containerSelector),
      ).map((container) => {
        container.addEventListener(
          ListFilter.FILTER_EVENT,
          this._boundFilterTableList,
        );
        return {
          container,
          filterItems: this.filterItemsBuilder(
            container,
            this.itemsSelector,
            this.targetsSelector,
            this.normalizeString,
          ),
          labels: container.querySelectorAll('[data-filter-group-name]'),
        };
      });

      this._dispatchFilterEventByContainer = (c, search) => {
        c.container.dispatchEvent(
          new CustomEvent(ListFilter.FILTER_EVENT, {
            detail: {
              query: search,
              allItems: c.filterItems,
              labels: c.labels,
            },
          }),
        );
      };

      this._inputHandler = Drupal.debounce((event) => {
        this.containers.forEach((c) =>
          this._dispatchFilterEventByContainer(c, event.target.value),
        );
      }, 100);

      this._keyDownHandler = (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          event.stopPropagation();
        }
      };

      this.input.addEventListener('input', this._inputHandler);
      this.input.addEventListener('keydown', this._keyDownHandler);
    }

    /**
     * Filters the table/list based on the input event.
     *
     * @param {Event} event
     *   The input event.
     */
    _filterTableList(event) {
      const { allItems, labels, query: queryParam } = event.detail;
      const query = this.normalizeString(queryParam);
      const toShow = [];
      const toHide = [];
      const visibleGroups = {};

      if (query.length >= this.minLength) {
        allItems.forEach((item) => {
          if (
            this.searchMethod(item.textContent, query, this.searchStartOfWords)
          ) {
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
            ListFilter._labelCollapse(label, false);
          });
          this.announce(
            toShow.length,
            this.announceSingular,
            this.announcePlural,
          );
        });
      } else {
        allItems.forEach((item) => {
          toShow.push(item.node);
        });
        requestAnimationFrame(() => {
          toShow.forEach((node) => this.showElement(node));
          labels.forEach((label) => this.showElement(label));
          Drupal.announce(this.announceFull);
          labels.forEach((label) => {
            ListFilter._labelCollapse(label, true);
          });
        });
      }

      // Dispatch a custom event to signal that searching has finished.
      requestAnimationFrame(() => {
        this.input.dispatchEvent(
          new CustomEvent(ListFilter.SEARCH_FINISHED_EVENT, {
            detail: {
              matched: toShow,
              unmatched: toHide,
            },
          }),
        );
      });
    }

    /**
     * Hides an element visually and semantically.
     * @param {HTMLElement} element
     * @returns {HTMLElement}
     */
    static _hideElementImpl(element) {
      element.style.display = 'none';
      return element;
    }

    /**
     * Shows an element visually and semantically.
     * @param {HTMLElement} element
     * @returns {HTMLElement}
     */
    static _showElementImpl(element) {
      element.style.display = '';
      return element;
    }

    /**
     * Normalizes a string for case-insensitive and accent-insensitive comparison.
     * @param {string} str
     * @returns {string}
     */
    static _normalizeStringImpl(str) {
      return slugify(str, { separator: ' ' });
    }

    /**
     * Returns a RegExp to match the start of a word.
     * @param {string} query
     * @returns {RegExp}
     */
    static _regexpStartsWith(query) {
      return new RegExp(`\\b${query}`);
    }

    /**
     * Determines if a target string matches the query.
     * @param {string} target
     * @param {string} query
     * @param {boolean} searchFromWordStart
     * @returns {boolean}
     */
    static _searchMethodImpl(target, query, searchFromWordStart) {
      return searchFromWordStart
        ? target.search(ListFilter._regexpStartsWith(query)) !== -1
        : target.includes(query);
    }

    /**
     * Announces the number of matches for accessibility.
     * @param {number} matches
     * @param {string} singularLabel
     * @param {string} pluralLabel
     */
    static _announceImpl(matches, singularLabel, pluralLabel) {
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
    static _filterItemsBuilderImpl(
      containerElement,
      itemsSelector,
      targetSelector,
      normalizeString,
    ) {
      return Array.from(containerElement.querySelectorAll(itemsSelector)).map(
        (source) => {
          const textContent = targetSelector
            ? Array.from(source.querySelectorAll(targetSelector))
                .map((t) => t.textContent)
                .join(' ')
            : source.textContent;
          return {
            node: source,
            textContent: normalizeString(textContent),
          };
        },
      );
    }

    /**
     * Expands or collapses a <details> label as needed.
     * @param {*} label
     * @param {boolean} reset
     */
    static _labelCollapse(label, reset = false) {
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
     * Resets the filter input and shows all items.
     */
    reset() {
      this.input.value = '';
      this.containers.forEach((c) =>
        this._dispatchFilterEventByContainer(c, ''),
      );
    }

    /**
     * Destroys the ListFilter instance by removing event listeners.
     */
    destroy() {
      this.reset();
      ListFilter.instances.delete(this.input);
      this.input.removeEventListener('keydown', this._keyDownHandler);
      this.input.removeEventListener('input', this._inputHandler);
      this.containers.forEach((c) => {
        c.container.removeEventListener(
          ListFilter.FILTER_EVENT,
          this._boundFilterTableList,
        );
      });
      this.containers = null;
    }
  }

  ListFilter.instances = new WeakMap();
  // Expose the class to Drupal namespace
  Drupal.ListFilter = ListFilter;
})(Drupal, window.slugify);
