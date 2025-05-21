/**
 * @file
 * Provide list filtering capabilities to admin UIs.
 */

(function ($, Drupal, drupalSettings) {
  /**
   * Filter lists or tables of text.
   *
   * Using the list_filter render element, any collection of items may be
   * filtered. Items which do not match the text entered into the filter
   * search box are hidden.
   *
   * The collection may optionally be grouped with either containing elements or
   * headers. When groups are present, if the result of a filter hides all the
   * rows belonging to a group, then the group is also hidden.
   *
   * The collection container, items, text source elements, and grouping
   * elements are specified as CSS selectors in the configuration of the
   * list_filter render element.
   *
   * Created listFilter instances may be modified with custom behaviors by
   * overriding the prototype methods. See the core/drupal.list-filter.details
   * and core/drupal.list-filter.sibling-groups libraries for examples of how to
   * do this.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.listFilter = {
    attach(context, settings) {
      function initListFilter($container, base) {
        Drupal.listFilter[base] = new Drupal.listFilter(
          $container,
          settings.listFilter[base],
        );
      }

      // Find all listFilter configurations placed in drupalSettings by
      // list_filter render elements.
      Object.keys(settings.listFilter || {}).forEach((base) => {
        initListFilter($(once('listFilter', `#${base}`, context)), base);
      });
    },
  };

  /**
   * Provides filtering of collections.
   *
   * @constructor
   *
   * @param {jQuery} $container
   *   jQuery object for the container to search inside.
   * @param {object} listFilterSettings
   *   Settings for the filtering added via the list_filter render element.
   */
  Drupal.listFilter = function ($container, listFilterSettings) {
    /**
     * The list filter settings, from the render element.
     */
    this.listFilterSettings = listFilterSettings;

    /**
     * jQuery object for the filter search box.
     */
    const $input = $('#' + listFilterSettings.search_field_id);

    /**
     * jQuery object for the filter container.
     *
     * All items and groups are within this container.
     */
    this.$container = $container;

    /**
     * jQuery object for the rows to search in.
     */
    this.$rows;

    /**
     * Array of text to search in. Indexes are the same as this.$rows.
     */
    this.sources = [];

    /**
     * jQuery object of groups.
     */
    this.$groups = listFilterSettings.list_group ?
      this.$container.find(listFilterSettings.list_group) :
      null;

    /**
     * Nested array of jQuery row objects. The outer indexes match the indexes
     * of this.$groups.
     */
    this.rowsByGroup = [];

    function preventEnterKey(event) {
      if (event.which === 13) {
        event.preventDefault();
        event.stopPropagation();
      }
    }

    // Initialize rows.
    this.$rows = this.$container.find(listFilterSettings.list_item);

    // Assemble text sources and if applicable, groups.
    if (this.$rows.length) {
      this.$rows.each(function (index, row) {
        let $row = $(row);

        // Assemble a nested array of rows by group.
        if (this.$groups) {
          let groupIndex = this.getRowGroup($row, this.$groups);
          this.rowsByGroup[groupIndex] ??= [];
          this.rowsByGroup[groupIndex].push($row);
        }

        // Build up an array of text content, with the same indexes as the $rows.
        // This is to avoid having to concatenate any multiple text items on
        // every search.
        let $sources = listFilterSettings.list_text ?
          $row.find(listFilterSettings.list_text) :
          $row;

        // Concatenate the textContent of the elements in the row, with a
        // space in between.
        let sourcesConcat = '';
        $sources.each((index, item) => {
          sourcesConcat += ' ' + item.textContent;
        });

        this.sources[index] = sourcesConcat;
      }.bind(this));

      // Filter rows when search text is entered.
      $input.on({
        input: Drupal.debounce(jQuery.proxy(this.filterUpdate, this), 200),
        click: Drupal.debounce(jQuery.proxy(this.filterUpdate, this), 200),
        keydown: preventEnterKey,
      });
    }

    if (listFilterSettings.debug) {
      this.$container.css('border', 'red 2px dotted');
      this.$rows.css('background-color', 'yellow');
      if (this.$groups) {
        this.$groups.css('border', 'green 2px dotted');
      }
    }
  };

  /**
    * Gets the group index for a row.
    *
    * @param {jQuery} $row
    *  A row jQuery object.
    * @param {jQuery} $groups
    *  The jQuery object of all groups.
    *
    * @return {number}
    *  The index for the group the row belongs to.
    */
  Drupal.listFilter.prototype.getRowGroup = function ($row, $groups) {
    let foundIndex = null;
    $groups.each((index, group) => {
      if (jQuery.contains(group, $row[0])) {
        foundIndex = index;
        // Break the each() loop.
        return false;
      }
    });
    return foundIndex;
  }

  /**
   * Acts in response to typing in the search box.
   *
   * @param {*} e
   *  The event.
   */
  Drupal.listFilter.prototype.filterUpdate = function (e) {
    const query = e.target.value;

    // Reset when the textbox is cleared.
    if (query.length === 0) {
      this.reset();
      return;
    }

    // Filter if the length of the query is at least the minimum number of
    // characters.
    if (query.length >= this.listFilterSettings.minimum_filter_length) {
      this.filterList(query);
      return;
    }
  }

  /**
   * Resets the list to show all items and groups.
   */
  Drupal.listFilter.prototype.reset = function () {
    this.$rows.show();
    this.showAllGroups();

    Drupal.announce(this.listFilterSettings.announce.all);
  }

  /**
   * Acts when filter text is entered, before filtering is performed.
   *
   * This allows libraries which extend this to act.
   */
  Drupal.listFilter.prototype.preFilter = function () { };

  /**
   * Filters the list.
   *
   * @param {string} query
   *  The text entered in the filter search box.
   */
  Drupal.listFilter.prototype.filterList = function (query) {
    this.preFilter();

    var re;
    if (this.listFilterSettings.search_start_of_words) {
      // Case insensitive expression to find query at the beginning of a word.
      re = new RegExp(`\\b${query}`, 'i');
    }
    else {
      // Case insensitive expression to find query anywhere in the text.
      re = new RegExp(query, 'i');
    }

    let visibleCount = 0;

    // Search in all of the rows' sources and show or hide accordingly.
    this.sources.forEach((source, index) => {
      const match = source.search(re) !== -1;

      this.$rows.eq(index).toggle(match);

      visibleCount += + match;
    });

    this.hideEmptyGroups();

    Drupal.announce(
      Drupal.formatPlural(
        visibleCount,
        this.listFilterSettings.announce.singular,
        this.listFilterSettings.announce.plural,
      ),
    );
  };

  /**
   * Hides all groups which have no rows showing for the current filter.
   */
  Drupal.listFilter.prototype.hideEmptyGroups = function () {
    this.$groups && this.$groups.each((index, group) => {
      let showGroup = this.rowsByGroup[index].reduce(
        (accumulator, $row) => {
          // Don't use .is(":visible") as that considers visibility of ancestors
          // as well, and we want to know specifically if the row has been
          // hidden.
          return accumulator || $row.css("display") != "none";
        },
        false
      );

      $(this.$groups[index]).toggle(showGroup);
    });
  }

  /**
   * Shows all groups.
   */
  Drupal.listFilter.prototype.showAllGroups = function () {
    this.$groups && this.$groups.show();
  }
})(jQuery, Drupal, drupalSettings);
