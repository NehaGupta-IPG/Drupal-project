/**
 * @file
 * Provides autocomplete functionality for link field widgets.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Override jQuery UI _renderItem function to output HTML by default.
   *
   * This uses function() syntax as required by jQuery UI.
   *
   * @param {object} ul
   *   The <ul> element that the newly created <li> element must be appended to.
   * @param {object} item
   *  The list item to append.
   *
   * @return {object}
   *   jQuery collection of the ul element.
   */
  function renderItem(ul, item) {
    const $line = $('<li class="entity-link-suggestions-result-line">');
    const $wrapper = $(
      '<div class="entity-link-suggestions-result-line-wrapper">',
    );
    $wrapper.append(
      `<span class="entity-link-suggestions-result-line--title">${Drupal.checkPlain(item.label)}</span>`,
    );
    if (item.hasOwnProperty('description') && item.description) {
      $wrapper.append(
        `<span class="entity-link-suggestions-result-line--description">${Drupal.checkPlain(item.description)}</span>`,
      );
    }
    return $line.append($wrapper).appendTo(ul);
  }

  /**
   * Override jQuery UI _renderMenu function to handle groups.
   *
   * This uses function() syntax as required by jQuery UI.
   *
   * @param {object} ul
   *   An empty <ul> element to use as the widget's menu.
   * @param {array} items
   *   An Array of items that match the user typed term.
   */
  function renderMenu(ul, items) {
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
        ul.append(
          `<li class="entity-link-suggestions-result-line--group ui-menu-divider">${Drupal.checkPlain(groupLabel)}</li>`,
        );
      }
      groupItems.forEach((item) => {
        this.element.autocomplete('instance')._renderItemData(ul, item);
      });
    });
  }

  /**
   * Initializes autocomplete for link field widgets.
   *
   * @param {jQuery} element
   *   The input element to attach autocomplete to.
   * @param {object} settings
   *   Settings object containing:
   *   - autocompleteUrl: The URL for autocomplete requests
   *   - selectHandler: Function to call when an item is selected
   *   - queryParams: Additional query parameters to send
   */
  function initializeAutocomplete(element, settings) {
    const {
      autocompleteUrl,
      selectHandler,
      queryParams,
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
        const data = Object.assign({}, queryParams);
        data.q = term;
        $.ajax(autocompleteUrl, {
          success: sourceCallbackHandler,
          data,
          ...autocomplete.ajax,
        });
      }
    }

    const options = {
      appendTo: element.closest('.form-item'),
      source: sourceData,
      select: selectHandler,
      focus: () => false,
      minLength: 1,
      isComposing: false,
    };
    const $auto = $(element).autocomplete(options);

    // Override a few things.
    const instance = $auto.data('ui-autocomplete');
    instance
      .widget()
      .menu(
        'option',
        'items',
        '> :not(.entity-link-suggestions-result-line--group)',
      );
    instance._renderMenu = renderMenu;
    instance._renderItem = renderItem;

    $auto
      .autocomplete('widget')
      .addClass('entity-link-suggestions-ui-autocomplete');

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
  }

  /**
   * Attaches autocomplete behavior to link field widgets.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches autocomplete to link field widgets.
   */
  Drupal.behaviors.linkAutocompleteWidget = {
    attach: function (context, settings) {
      $(once('link-autocomplete-widget', '.link-autocomplete-widget-uri', context)).each(function () {
        const $uriInput = $(this);
        const $widget = $uriInput.closest('.link-autocomplete-widget-container');
        const $titleInput = $widget.find('input.link-autocomplete-widget-title');
        const autocompleteUrl = $uriInput.data('autocomplete-url');
        const hostEntityTypeId = $uriInput.data('host-entity-type-id') || '';
        const hostEntityLangcode = $uriInput.data('host-entity-langcode') || '';

        if (!autocompleteUrl) {
          return;
        }

        initializeAutocomplete($uriInput, {
          autocompleteUrl: autocompleteUrl,
          queryParams: {
            hostEntityTypeId: hostEntityTypeId,
            hostEntityLangcode: hostEntityLangcode,
          },
          selectHandler: function (event, ui) {
            event.preventDefault();
            const item = ui.item;

            if (!item.path && !item.href) {
              return;
            }

            // Set the URI value (use path for display, href for storage).
            const displayValue = item.path || item.href || '';
            $uriInput.val(displayValue);

            // Set the href value for storage.
            const hrefValue = item.href || item.path || '';
            $widget.find('[name$="[attributes][href]"]').val(hrefValue);

            // Set entity metadata if available.
            if (item.entity_type_id && item.entity_uuid) {
              $widget.find('[name$="[attributes][data-entity-type]"]').val(item.entity_type_id);
              $widget.find('[name$="[attributes][data-entity-uuid]"]').val(item.entity_uuid);
              $widget.find('[name$="[attributes][data-entity-substitution]"]').val(item.entity_type_id === 'file' ? 'file' : 'canonical');
            } else {
              $widget.find('[name$="[attributes][data-entity-type]"]').val('');
              $widget.find('[name$="[attributes][data-entity-uuid]"]').val('');
              $widget.find('[name$="[attributes][data-entity-substitution]"]').val('');
            }

            // Auto-fill title if enabled and empty.
            const autofillEnabled = $titleInput.length && (
              $titleInput.attr('data-autofill-enabled') === '' ||
              $titleInput.attr('data-autofill-enabled') === 'true'
            );
            if (autofillEnabled && !$titleInput.val() && item.label) {
              // The item label may have been sanitized for display as HTML. We want this back in the original format so that
              // characters are not double encoded (e.g. we want "foo &amp; bar" to be "foo & bar").
              const label = document.createElement('span');
              label.innerHTML = item.label;
              $titleInput.val(label.textContent);
            }

            // Trigger change event.
            $uriInput.trigger('change');
          },
        });
      });
    },
  };

})(jQuery, Drupal);

