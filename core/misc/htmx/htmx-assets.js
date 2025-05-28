/**
 * @file
 * Adds assets the current page requires.
 *
 * This script fires a custom `htmx:drupal:load` event when the request has
 * settled and all script and css files have been successfully loaded on the
 * page.
 */

(function (Drupal, drupalSettings, htmx) {
  // Disable htmx loading of script tags since we're handling it.
  htmx.config.allowScriptTags = false;

  /**
   * Used to hold the loadjs promise.
   *
   * It's declared in htmx:beforeSwap and checked in htmx:afterSettle to trigger
   * the custom htmx:drupal:load event.
   *
   * @type {WeakMap<XMLHttpRequest, Promise>}
   */
  const requestAssetsLoaded = new WeakMap();

  /**
   * Send the current ajax page state with each request.
   *
   * @param configRequestEvent
   *   HTMX event for request configuration.
   *
   * @see system_js_settings_alter()
   * @see \Drupal\Core\Render\HtmlResponseAttachmentsProcessor::processAttachments
   * @see https://htmx.org/api/#on
   * @see https://htmx.org/events/#htmx:configRequest
   */
  htmx.on('htmx:configRequest', ({ detail }) => {
    const url = new URL(detail.path, document.location.href);
    if (Drupal.url.isLocal(url.toString())) {
      // Allow Drupal to return new JavaScript and CSS files to load without
      // returning the ones already loaded.
      // @see \Drupal\Core\StackMiddleWare\AjaxPageState
      // @see \Drupal\Core\Theme\AjaxBasePageNegotiator
      // @see \Drupal\Core\Asset\LibraryDependencyResolverInterface::getMinimalRepresentativeSubset()
      // @see system_js_settings_alter()
      const pageState = drupalSettings.ajaxPageState;
      detail.parameters['ajax_page_state[theme]'] = pageState.theme;
      detail.parameters['ajax_page_state[theme_token]'] = pageState.theme_token;
      detail.parameters['ajax_page_state[libraries]'] = pageState.libraries;
    }
  });

  // @see https://htmx.org/events/#htmx:beforeSwap
  htmx.on('htmx:beforeSwap', ({ detail }) => {
    // Custom event to detach behaviors.
    htmx.trigger(detail.elt, 'htmx:drupal:unload');

    if (!detail.xhr) {
      return;
    }

    // We need to parse the response to find all the assets to load.
    // htmx cleans up too many things to be able to rely on their dom fragment.
    let responseHTML = Document.parseHTMLUnsafe(detail.serverResponse);

    // Update drupalSettings
    // Use direct child elements to harden against XSS exploits when CSP is on.
    const settingsElement = responseHTML.querySelector(
      ':is(head, body) > script[type="application/json"][data-drupal-selector="drupal-settings-json"]',
    );
    if (settingsElement !== null) {
      Drupal.htmx.mergeSettings(
        drupalSettings,
        JSON.parse(settingsElement.textContent),
      );
    }

    // Load all assets files. We sent ajax_page_state in the request so this is only the diff with the current page.
    const assetsTags = responseHTML.querySelectorAll(
      'link[rel="stylesheet"][href], script[src]',
    );
    // Transform the data from the DOM into an ajax command like format.
    const data = Array.from(assetsTags).map(({ attributes }) => attributes);

    // Helps with memory management.
    responseHTML = null;

    requestAssetsLoaded.set(detail.xhr, Drupal.htmx.addAssets(data));
  });

  // Trigger the Drupal processing once all assets have been loaded.
  // @see https://htmx.org/events/#htmx:afterSettle
  htmx.on('htmx:afterSettle', ({ detail }) => {
    (requestAssetsLoaded.get(detail.xhr) || Promise.resolve()).then(() => {
      htmx.trigger(detail.elt, 'htmx:drupal:load');
      // This should be automatic but don't wait for the garbage collector.
      requestAssetsLoaded.delete(detail.xhr);
    });
  });
})(Drupal, drupalSettings, htmx);
