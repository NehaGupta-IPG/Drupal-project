/**
 * @file
 * Shared autocomplete functionality for entity link suggestions.
 */

(function ($, Drupal) {
  /**
   * Override jQuery UI _renderItem function to output HTML by default.
   *
   * This uses function() syntax as required by jQuery UI.
   *
   * @param {object} ul
   *   The <ul> element that the newly created <li> element must be appended to.
   * @param {object} item
   *  The list item to append.
   * @param {object} options
   *   Options object with:
   *   - escapeHtml: Whether to escape HTML in labels (default: true).
   *
   * @return {object}
   *   jQuery collection of the ul element.
   */
  Drupal.entityLinkSuggestionsRenderItem = function (ul, item, options = {}) {
    const escapeHtml = options.escapeHtml !== false;
    const $ = jQuery;
    const $line = $(
      '<li class="entity-link-suggestions-result-line ui-menu-item">',
    );
    const $wrapper = $(
      '<div class="entity-link-suggestions-result-line-wrapper">',
    );
    const label =
      escapeHtml && Drupal.checkPlain
        ? Drupal.checkPlain(item.label)
        : item.label;
    $wrapper.append(
      `<span class="entity-link-suggestions-result-line--title">${label}</span>`,
    );
    if (item.hasOwnProperty('description') && item.description) {
      const description =
        escapeHtml && Drupal.checkPlain
          ? Drupal.checkPlain(item.description)
          : item.description;
      $wrapper.append(
        `<span class="entity-link-suggestions-result-line--description">${description}</span>`,
      );
    }
    return $line.append($wrapper).appendTo(ul);
  };

  /**
   * Override jQuery UI _renderMenu function to handle groups.
   *
   * This uses function() syntax as required by jQuery UI.
   *
   * @param {object} ul
   *   An empty <ul> element to use as the widget's menu.
   * @param {array} items
   *   An Array of items that match the user typed term.
   * @param {object} options
   *   Options object with:
   *   - escapeHtml: Whether to escape HTML in group labels (default: true).
   */
  Drupal.entityLinkSuggestionsRenderMenu = function (ul, items, options = {}) {
    const escapeHtml = options.escapeHtml !== false;
    const $ = jQuery;
    const groupedItems = {};
    items.forEach((item) => {
      const group = item.hasOwnProperty('group') ? item.group : '';
      if (!groupedItems.hasOwnProperty(group)) {
        groupedItems[group] = [];
      }
      groupedItems[group].push(item);
    });

    Object.keys(groupedItems).forEach((groupLabel) => {
      const groupItems = groupedItems[groupLabel];
      if (groupLabel.length) {
        const label =
          escapeHtml && Drupal.checkPlain
            ? Drupal.checkPlain(groupLabel)
            : groupLabel;
        ul.append(
          `<li class="entity-link-suggestions-result-line--group ui-menu-divider">${label}</li>`,
        );
      }
      groupItems.forEach((item) => {
        this.element.autocomplete('instance')._renderItemData(ul, item);
      });
    });
  };

  /**
   * Initializes autocomplete for entity link suggestions.
   *
   * @param {jQuery} element
   *   The input element to attach autocomplete to.
   * @param {object} settings
   *   Settings object containing:
   *   - autocompleteUrl: The URL for autocomplete requests
   *   - selectHandler: Function to call when an item is selected
   *   - queryParams: Additional query parameters to send
   *   - appendTo: jQuery selector or element to append menu to (default: closest .form-item)
   *   - closeHandler: Optional function to call when menu closes
   *   - openHandler: Optional function to call when menu opens
   *   - widgetClass: Additional CSS class for the autocomplete widget
   *   - escapeHtml: Whether to escape HTML in labels (default: true)
   *
   * @return {jQuery}
   *   The jQuery autocomplete instance.
   */
  Drupal.entityLinkSuggestionsAutocomplete = function (element, settings) {
    const $ = jQuery;
    const {
      autocompleteUrl,
      selectHandler,
      queryParams,
      appendTo,
      closeHandler,
      openHandler,
      widgetClass = '',
      escapeHtml = true,
    } = settings;
    const autocomplete = {
      cache: {},
      ajax: {
        dataType: 'json',
        jsonp: false,
      },
    };

    /**
     * JQuery UI autocomplete source callback.
     *
     * @param {object} request
     *   The request object.
     * @param {function} response
     *   The function to call with the response.
     */
    function sourceData(request, response) {
      const { cache } = autocomplete;
      const { term } = request;

      /**
       * Transforms the data object into an array and update autocomplete results.
       *
       * @param {object} data
       *   The data sent back from the server.
       */
      function sourceCallbackHandler(data) {
        cache[term] = data.suggestions;
        response(data.suggestions);
      }

      // Check if the term is already cached.
      if (cache.hasOwnProperty(term)) {
        response(cache[term]);
      } else {
        const data = { ...queryParams, q: term };
        $.ajax(autocompleteUrl, {
          success: sourceCallbackHandler,
          data,
          ...autocomplete.ajax,
        });
      }
    }

    const defaultAppendTo = appendTo || element.closest('.form-item');
    const options = {
      appendTo: defaultAppendTo,
      source: sourceData,
      select: selectHandler,
      focus: () => false,
      minLength: 1,
      isComposing: false,
    };

    // Add optional handlers.
    if (closeHandler) {
      options.close = closeHandler;
    }
    if (openHandler) {
      options.open = openHandler;
    }
    // Add search option if closeHandler is provided (CKEditor5 pattern).
    if (closeHandler) {
      options.search = () => !options.isComposing;
    }

    const $auto = $(element).autocomplete(options);

    // Override a few things.
    const instance = $auto.data('ui-autocomplete');
    const renderOptions = { escapeHtml };
    instance
      .widget()
      .menu(
        'option',
        'items',
        '> :not(.entity-link-suggestions-result-line--group)',
      );
    instance._renderMenu = function (ul, items) {
      Drupal.entityLinkSuggestionsRenderMenu.call(
        this,
        ul,
        items,
        renderOptions,
      );
    };
    instance._renderItem = function (ul, item) {
      return Drupal.entityLinkSuggestionsRenderItem.call(
        this,
        ul,
        item,
        renderOptions,
      );
    };

    const classes = `entity-link-suggestions-ui-autocomplete${widgetClass ? ` ${widgetClass}` : ''}`;
    $auto.autocomplete('widget').addClass(classes);

    $auto.on('click', () => {
      $auto.autocomplete('search', $auto[0].value);
    });

    // Use CompositionEvent to handle IME inputs. It requests remote server on "compositionend" event only.
    $auto.on('compositionstart.autocomplete', () => {
      options.isComposing = true;
    });
    $auto.on('compositionend.autocomplete', () => {
      options.isComposing = false;
    });

    return $auto;
  };
})(jQuery, Drupal);
