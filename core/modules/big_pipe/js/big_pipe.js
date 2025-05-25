/**
 * @file
 * Renders BigPipe placeholders using Drupal's Ajax system.
 */

((Drupal, drupalSettings) => {
  Drupal.bigPipeLoad = function (chunk) {
    const template = chunk.previousElementSibling;
    const id = template.dataset.bigPipeReplacementForPlaceholderWithId;

    // Ignore any placeholders that are not in the known placeholder list. Used
    // to avoid someone trying to XSS the site via the placeholdering mechanism.
    if (typeof drupalSettings.bigPipePlaceholderIds[id] === 'undefined') {
      return;
    }

    // Immediately remove the replacement to prevent it being processed twice.
    delete drupalSettings.bigPipePlaceholderIds[id];

    const target = htmx.find(
      `[data-big-pipe-placeholder-id="${CSS.escape(id)}"]`,
    );
    const newHtml = template.innerHTML;
    // This is not used, only for consistency in the various event listeners
    const xhr = new XMLHttpRequest();

    // Because we don't have a real xhr request, we need to trigger our event
    // listeners handling assets loading manually.
    htmx.trigger(target, 'htmx:beforeSwap', {
      serverResponse: newHtml,
      xhr,
    });

    // Make the actual swap and initialize everything.
    htmx.swap(
      target,
      newHtml,
      { swapStyle: 'outerHTML' },
      { eventInfo: { xhr } },
    );

    // Clean up the page.
    chunk.remove();
    template.remove();
  };

  // Attach behaviors early, if possible.
  setTimeout(() => {
    Drupal.attachBehaviors(document);
  }, 20);
})(Drupal, drupalSettings);
