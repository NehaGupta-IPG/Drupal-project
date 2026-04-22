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
    attach(context) {
      const iconInputs = once(
        'icon-autocomplete-preview',
        '.form-icon-autocomplete',
        context,
      );

      iconInputs.forEach((input) => {
        const wrapper = input.closest('.icon-wrapper');
        if (!wrapper) {
          return;
        }

        const preview = wrapper.querySelector('.icon-preview');
        if (!preview) {
          return;
        }

        // Update preview when input value changes (with debounce).
        let debounceTimer;
        input.addEventListener('input', () => {
          clearTimeout(debounceTimer);
          debounceTimer = setTimeout(() => {
            updateIconPreview(input.value, preview);
          }, 300);
        });

        // Update preview on blur (when user leaves field).
        input.addEventListener('blur', () => {
          updateIconPreview(input.value, preview);
        });

        // Listen for autocomplete selection using jQuery (Drupal autocomplete
        // uses jQuery).
        if (window.jQuery) {
          jQuery(input).on('autocompleteselect', (event, ui) => {
            // Small delay to ensure value is updated.
            setTimeout(() => {
              updateIconPreview(ui.item.value, preview);
            }, 100);
          });
        }

        // Initialize preview with current value.
        if (input.value) {
          updateIconPreview(input.value, preview);
        }
      });
    },
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
    // Clear current preview.
    previewElement.innerHTML = '';

    if (!iconId || !iconId.includes(':')) {
      return;
    }

    const [packId, iconName] = iconId.split(':', 2);

    // Use Drupal's icon render element via AJAX.
    const baseUrl = drupalSettings.path.baseUrl || '/';
    fetch(
      `${baseUrl}system/icon-render?pack_id=${encodeURIComponent(packId)}&icon_id=${encodeURIComponent(iconName)}`,
    )
      .then((response) => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.json();
      })
      .then((data) => {
        if (data.svg) {
          previewElement.innerHTML = data.svg;
        }
      })
      .catch(() => {
        // Silently fail — preview is non-critical.
      });
  }
})(Drupal, once, drupalSettings);
