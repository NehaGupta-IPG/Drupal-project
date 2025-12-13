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

        /**
         * Helper function for getting one of the "attributes" input elements.
         *
         * @param {string} name
         *   The name of the input within the attributes group.
         * @param {jQuery} $context
         *   The element search context.
         *
         * @return {jQuery}
         *   The selected element.
         */
        function getAttributesInput(name, $context) {
          return $context.find(
            `input[name="attributes[${name}]"], input[name$="[attributes][${name}]"]`,
          );
        }

        /**
         * Sets entity metadata fields from an object.
         *
         * Only sets values that are provided in the metadata object. If a property
         * is not provided, the existing value is not overridden.
         *
         * @param {Object} metadata
         *   Object with optional properties:
         *   - entityType: The entity type ID.
         *   - entityUuid: The entity UUID.
         *   - entitySubstitution: The substitution type ('file' or 'canonical').
         * @param {jQuery} $context
         *   The element search context.
         */
        function setEntityMetadata(metadata, $context) {
          if (metadata.hasOwnProperty('entityType')) {
            getAttributesInput('data-entity-type', $context)[0].value =
              metadata.entityType || '';
          }
          if (metadata.hasOwnProperty('entityUuid')) {
            getAttributesInput('data-entity-uuid', $context)[0].value =
              metadata.entityUuid || '';
          }
          if (metadata.hasOwnProperty('entitySubstitution')) {
            getAttributesInput('data-entity-substitution', $context)[0].value =
              metadata.entitySubstitution || '';
          }
        }

        // In case the user makes an edit and does not click on the autocomplete
        // dropdown (so selectHandler() does not run), add a listener to update
        // the hidden form inputs.
        $uriInput.on('focusout', function () {
          const $context = $uriInput.closest('form, fieldset, tr');
          const $hrefInput = getAttributesInput('href', $context);

          if (!$hrefInput.length || !$uriInput[0].value) {
            return;
          }

          // Compare URL properties to detect manual changes (more robust than string comparison).
          try {
            const href = new URL($hrefInput[0].value || '', document.baseURI);
            const uri = new URL($uriInput[0].value, document.baseURI);
            const URLpropsToCheck = [
              'auth',
              'host',
              'hostname',
              'pathname',
              'protocol',
              'slashes',
              'port',
            ];

            // If any URL property differs, the user typed manually - clear entity metadata.
            if (URLpropsToCheck.some((prop) => href[prop] !== uri[prop])) {
              // Clear entity metadata when user types manually.
              setEntityMetadata(
                {
                  entityType: '',
                  entityUuid: '',
                  entitySubstitution: '',
                },
                $context,
              );
            }
          } catch (e) {
            // If URL parsing fails, assume manual input and clear metadata.
            setEntityMetadata(
              {
                entityType: '',
                entityUuid: '',
                entitySubstitution: '',
              },
              $context,
            );
          }

          // Always ensure href is up to date (handles fragments/query strings).
          $hrefInput[0].value = $uriInput[0].value;
        });

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
            const $context = $uriInput.closest('form, fieldset, tr');
            const $hrefInput = getAttributesInput('href', $context);
            if ($hrefInput.length) {
              $hrefInput[0].value = hrefValue;
            }

            // Set entity metadata if available.
            if (item.entity_type_id && item.entity_uuid) {
              setEntityMetadata(
                {
                  entityType: item.entity_type_id,
                  entityUuid: item.entity_uuid,
                  entitySubstitution:
                    item.entity_type_id === 'file' ? 'file' : 'canonical',
                },
                $context,
              );
            } else {
              setEntityMetadata(
                {
                  entityType: '',
                  entityUuid: '',
                  entitySubstitution: '',
                },
                $context,
              );
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
