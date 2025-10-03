/**
 * @file
 * Icon autocomplete behavior for previewing selected icons.
 */

(function (Drupal, once, drupalSettings) {
  'use strict';

  /**
   * Behavior for icon autocomplete preview.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.iconAutocompletePreview = {
    attach: function (context) {
      const iconInputs = once('icon-autocomplete-preview', '.form-icon-autocomplete', context);

      iconInputs.forEach(function (input) {
        const wrapper = input.closest('.icon-wrapper');
        if (!wrapper) {
          console.log('No wrapper found for input', input);
          return;
        }

        const preview = wrapper.querySelector('.icon-preview');
        if (!preview) {
          console.log('No preview element found in wrapper', wrapper);
          return;
        }

        console.log('Icon autocomplete initialized', input.id);

        // Update preview when input value changes (with debounce)
        let debounceTimer;
        input.addEventListener('input', function () {
          clearTimeout(debounceTimer);
          debounceTimer = setTimeout(function() {
            console.log('Input changed:', input.value);
            updateIconPreview(input.value, preview);
          }, 300);
        });

        // Update preview on blur (when user leaves field)
        input.addEventListener('blur', function () {
          console.log('Input blur:', input.value);
          updateIconPreview(input.value, preview);
        });

        // Listen for autocomplete selection using jQuery (Drupal autocomplete uses jQuery)
        if (window.jQuery) {
          jQuery(input).on('autocompleteselect', function (event, ui) {
            console.log('Autocomplete select:', ui.item.value);
            // Small delay to ensure value is updated
            setTimeout(function() {
              updateIconPreview(ui.item.value, preview);
            }, 100);
          });
        }

        // Initialize preview with current value
        if (input.value) {
          console.log('Initial value:', input.value);
          updateIconPreview(input.value, preview);
        }
      });
    }
  };

  /**
   * Updates the icon preview element.
   *
   * @param {string} iconId
   *   The icon ID in format "pack_id:icon_id".
   * @param {HTMLElement} previewElement
   *   The preview container element.
   */
  function updateIconPreview(iconId, previewElement) {
    // Clear current preview
    previewElement.innerHTML = '';

    if (!iconId || !iconId.includes(':')) {
      console.log('Invalid icon ID:', iconId);
      return;
    }

    const [packId, iconName] = iconId.split(':', 2);
    console.log('Fetching icon:', packId, iconName);

    // Use Drupal's icon render element via AJAX
    const baseUrl = drupalSettings.path.baseUrl || '/';
    fetch(`${baseUrl}system/icon-render?pack_id=${encodeURIComponent(packId)}&icon_id=${encodeURIComponent(iconName)}`)
      .then(response => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.json();
      })
      .then(data => {
        console.log('Icon data received:', data);
        if (data.svg) {
          previewElement.innerHTML = data.svg;
        }
      })
      .catch(error => {
        console.error('Error loading icon:', error);
      });
  }

})(Drupal, once, drupalSettings);
