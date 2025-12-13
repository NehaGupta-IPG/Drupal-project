/**
 * @file
 * Provides autocomplete functionality for link field widgets.
 */

(function ($, Drupal, once) {
  /**
   * Attaches autocomplete behavior to link field widgets.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches autocomplete to link field widgets.
   */
  Drupal.behaviors.linkAutocompleteWidget = {
    attach(context, settings) {
      $(
        once(
          'link-autocomplete-widget',
          '.link-autocomplete-widget-uri',
          context,
        ),
      ).each(function () {
        const $uriInput = $(this);
        const $widget = $uriInput.closest(
          '.link-autocomplete-widget-container',
        );
        const $titleInput = $widget.find(
          'input.link-autocomplete-widget-title',
        );
        const autocompleteUrl = $uriInput.data('autocomplete-url');
        const hostEntityTypeId = $uriInput.data('host-entity-type-id') || '';
        const hostEntityLangcode = $uriInput.data('host-entity-langcode') || '';

        if (!autocompleteUrl) {
          return;
        }

        Drupal.entityLinkSuggestionsAutocomplete($uriInput, {
          autocompleteUrl,
          queryParams: {
            hostEntityTypeId,
            hostEntityLangcode,
          },
          appendTo: $uriInput.closest('.form-item'),
          escapeHtml: true,
          selectHandler(event, ui) {
            event.preventDefault();
            const item = ui.item;

            if (!item.path && !item.href) {
              return;
            }

            // Set the URI value (use path for display, href for storage).
            const displayValue = item.path || item.href || '';
            $uriInput[0].value = displayValue;

            // Set the href value for storage.
            const hrefValue = item.href || item.path || '';
            $widget.find('[name$="[attributes][href]"]')[0].value = hrefValue;

            // Set entity metadata if available.
            if (item.entity_type_id && item.entity_uuid) {
              $widget.find(
                '[name$="[attributes][data-entity-type]"]',
              )[0].value = item.entity_type_id;
              $widget.find(
                '[name$="[attributes][data-entity-uuid]"]',
              )[0].value = item.entity_uuid;
              $widget.find(
                '[name$="[attributes][data-entity-substitution]"]',
              )[0].value =
                item.entity_type_id === 'file' ? 'file' : 'canonical';
            } else {
              $widget.find(
                '[name$="[attributes][data-entity-type]"]',
              )[0].value = '';
              $widget.find(
                '[name$="[attributes][data-entity-uuid]"]',
              )[0].value = '';
              $widget.find(
                '[name$="[attributes][data-entity-substitution]"]',
              )[0].value = '';
            }

            // Auto-fill title if enabled and empty.
            const autofillEnabled =
              $titleInput.length &&
              ($titleInput.attr('data-autofill-enabled') === '' ||
                $titleInput.attr('data-autofill-enabled') === 'true');
            if (autofillEnabled && !$titleInput[0].value && item.label) {
              // The item label may have been sanitized for display as HTML. We want this back in the original format so that
              // characters are not double encoded (e.g. we want "foo &amp; bar" to be "foo & bar").
              const label = document.createElement('span');
              label.innerHTML = item.label;
              $titleInput[0].value = label.textContent;
            }

            // Trigger change event.
            $uriInput.trigger('change');
          },
        });
      });
    },
  };
})(jQuery, Drupal, once);
