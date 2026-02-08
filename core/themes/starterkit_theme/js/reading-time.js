/**
 * @file
 * Defines a custom behavior.
 */

((Drupal, drupalSettings, once) => {
  // Constant variables for consistency.
  const onceName = 'readingTime';

  // Use any valid selector to target DOM elements.
  // Example: '.my-class' or 'article > h1'.
  // @see https://developer.mozilla.org/en-US/docs/Web/API/Document/querySelector#parameters
  const elementSelector = 'main[role="main"]';

  // Custom value coming from the backend.
  // @see starterkit_theme_attachments() in starterkit_theme.theme.
  const wordsPerMinute = drupalSettings.starterkit_theme.wordsPerMinute || 300;

  // Instantiate Drupal message command.
  // Note that it is possible because we required the `core/drupal.message` library.
  // @see core/themes/starterkit_theme/starterkit_theme.libraries.yml
  // @see https://www.drupal.org/docs/drupal-apis/javascript-api/messages-api
  const messages = new Drupal.Message();

  /**
   * Calculate how many minutes it will take to read the piece of content.
   *
   * This helper method is encapsulated in script.
   *
   * @param {HTMLElement} element
   *  A given DOM element.
   *
   * @param {number} wpm
   *  A given "words per minute" number.
   *
   * @return {number}
   *  The reading time, in minutes.
   */
  function readingTime(element, wpm) {
    const text = element.innerText;
    const words = text.trim().split(/\s+/).length;
    return Math.ceil(words / wpm);
  }

  /**
   * Log approximate reading time of main content in console.
   *
   * This registers the Drupal behaviors which is triggered on every page load and
   * when data is loaded by AJAX.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior to the rendering context, if possible.
   *
   * @see starterkit_theme.libraries.yml
   *   Where dependencies to core/drupalSettings and core/once are defined.
   * @see https://www.drupal.org/docs/drupal-apis/javascript-api/javascript-api-overview
   *   An introduction to the art of using JavaScript in Drupal.
   * @see https://www.drupal.org/node/3158256
   *   The change notice with examples of how to use once().
   * @see https://www.npmjs.com/package/@drupal/once
   *   The NPM package with the full documentation about once().
   */
  Drupal.behaviors.readingTime = {
    attach(context) {
      // Process the current content to calculate the reading time.
      // We use `once()` from core to avoid processing the content multiple time.
      once(onceName, elementSelector, context).forEach((element) => {
        const time = readingTime(element, wordsPerMinute);
        messages.add(
          Drupal.t('This page will take you @minutes to read', {
            '@minutes': Drupal.formatPlural(time, '1·minute', '@count·minutes'),
          }),
        );
      });
    },
    detach(context, trigger) {
      if (trigger === 'unload') {
        // Remove processing mark so that content can be processed again.
        // The text might have change after an Ajax call so reading time
        // will need to be recalculated.
        once.remove(onceName, elementSelector, context);
      }
    },
  };
})(Drupal, drupalSettings, once);
