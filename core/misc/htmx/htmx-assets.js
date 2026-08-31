/**
 * @file
 * Adds assets the current page requires.
 *
 * This script fires a custom `htmx:drupal:load` event when the request has
 * settled and all script and css files have been successfully loaded on the
 * page.
 */

(function (Drupal, drupalSettings, htmx) {
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
   * Transforms the attributes of a DOM element into a plain object.
   *
   * @param {Element} element
   *   The DOM element.
   *
   * @return {object}
   *   The attributes of the element, keyed by attribute name.
   */
  const elementAttributes = (element) => {
    const attrs = {};
    Object.values(element.attributes).forEach(({ name, value }) => {
      attrs[name] = value;
    });
    return attrs;
  };

  /**
   *
   */
  htmx.on('htmx:beforeRequest', ({ detail }) => {
    requestAssetsLoaded.set(detail.xhr, Promise.resolve());
  });

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
    if (Drupal.url.isLocal(detail.path)) {
      if (detail.elt.hasAttribute('data-hx-drupal-only-main-content')) {
        // Add _wrapper_format query parameter for all non full page requests.
        // Drupal expects this parameter to be in the query string, not the post
        // values.
        const url = new URL(detail.path, window.location);
        url.searchParams.set('_wrapper_format', 'drupal_htmx');
        detail.path = url.toString();
      }
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
      if (detail.headers['HX-Trigger-Name']) {
        detail.parameters._triggering_element_name =
          detail.headers['HX-Trigger-Name'];
      }
    }
  });

  // When saving to the browser history always remove wrapper format and ajax
  // page state from the query string.
  htmx.on('htmx:beforeHistoryUpdate', ({ detail }) => {
    const url = new URL(detail.history.path, window.location);
    [
      '_wrapper_format',
      'ajax_page_state[theme]',
      'ajax_page_state[theme_token]',
      'ajax_page_state[libraries]',
      '_triggering_element_name',
      '_triggering_element_value',
    ].forEach((key) => {
      url.searchParams.delete(key);
    });
    detail.history.path = url.toString();
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
    // Remove so that HTML doesn't add this during swap.
    settingsElement?.remove();

    if (settingsElement !== null) {
      Drupal.htmx.mergeSettings(
        drupalSettings,
        JSON.parse(settingsElement.textContent),
      );
    }

    // Load all assets files. We sent ajax_page_state in the request so this is only the diff with the current page.
    const assetsElements = responseHTML.querySelectorAll(
      'link[rel="stylesheet"][href], script[src]',
    );
    // Module preloads instruct the browser to fetch modules, so that module
    // scripts added by the response can start importing them as soon as
    // possible. They are not loaded with loadjs, but added to the head
    // directly. The import map that resolves their bare module specifiers was
    // already sent with the main HTML document.
    const preloadElements = responseHTML.querySelectorAll(
      'link[rel="modulepreload"][href]',
    );
    // Remove all assets from the serverResponse where we handle the loading.
    assetsElements.forEach((element) => element.remove());
    preloadElements.forEach((element) => element.remove());

    // Transform the data from the DOM into an ajax command like format.
    const data = Array.from(assetsElements).map(elementAttributes);

    // The response is the whole page without the assets we handle with loadjs.
    detail.serverResponse = responseHTML.documentElement.outerHTML;

    // Helps with memory management.
    responseHTML = null;

    requestAssetsLoaded.get(detail.xhr).then(() => Drupal.htmx.addAssets(data));

    // Add the module preloads to the head. A page only supports a single
    // import map, which was already sent with the main HTML document; module
    // preloads may be added to a page multiple times.
    Array.from(preloadElements)
      .map(elementAttributes)
      .forEach((attrs) => {
        // Do not add the same module preload twice.
        const alreadyPreloaded = Array.from(
          document.head.querySelectorAll('link[rel="modulepreload"]'),
        ).some((link) => link.getAttribute('href') === attrs.href);
        if (!alreadyPreloaded) {
          const link = document.createElement('link');
          Object.entries(attrs).forEach(([name, value]) => {
            link.setAttribute(name, value);
          });
          document.head.appendChild(link);
        }
      });
  });

  // Trigger the Drupal processing once all assets have been loaded.
  // @see https://htmx.org/events/#htmx:afterSettle
  htmx.on('htmx:afterSettle', ({ detail }) => {
    (requestAssetsLoaded.get(detail.xhr) || Promise.resolve()).then(() => {
      let processTarget = detail.elt.parentElement;
      // Some HTMX swaps put the incoming element before or after detail.elt.
      // We normally target the parent element so that we process the added
      // elements. When there is no parent element or detail.elt is the body,
      // we target the element itself.
      if (detail.elt === document.body || processTarget === null) {
        processTarget = detail.elt;
      }
      htmx.trigger(processTarget, 'htmx:drupal:load');
      // This should be automatic but don't wait for the garbage collector.
      requestAssetsLoaded.delete(detail.xhr);
    });
  });
})(Drupal, drupalSettings, htmx);
