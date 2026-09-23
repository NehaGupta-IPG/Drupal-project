/**
 * @file
 * Initializes autocomplete for CKEditor 5 entity link suggestions.
 *
 * This module wraps the shared entity link suggestions autocomplete functionality
 * with CKEditor 5-specific configuration.
 */

/**
 * Initializes autocomplete for entity link suggestions in CKEditor 5.
 *
 * @param {HTMLElement} element
 *   The input element to attach autocomplete to.
 * @param {object} settings
 *   Settings object containing:
 *   - autocompleteUrl: The URL for autocomplete requests
 *   - selectHandler: Function to call when an item is selected
 *   - closeHandler: Optional function to call when menu closes
 *   - openHandler: Optional function to call when menu opens
 *   - queryParams: Additional query parameters to send
 *
 * @return {jQuery}
 *   The jQuery autocomplete instance.
 */
export default function initializeAutocomplete(element, settings) {
  const {
    autocompleteUrl,
    selectHandler,
    closeHandler,
    openHandler,
    queryParams,
  } = settings;

  // Use the shared autocomplete functionality from the link module.
  // The Drupal and jQuery globals should be available via library dependencies.
  const $ = jQuery;
  return Drupal.entityLinkSuggestionsAutocomplete($(element), {
    autocompleteUrl,
    selectHandler,
    queryParams,
    appendTo: $(element).closest('.ck-labeled-field-view'),
    closeHandler,
    openHandler,
    widgetClass: 'ck-reset_all-excluded',
    escapeHtml: false,
  });
}
