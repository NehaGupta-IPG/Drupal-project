/**
 * @file
 * Maintains and deprecates displace jQuery event drupalViewportOffsetChange.
 */

(function ($, Drupal, once) {
  if (once('drupal-displace-deprecation-listener', 'html').length) {
    const eventSpecial = {
      handle($event) {
        const event = $event.originalEvent;
        const offsets = event?.offsets;
        const displaceArguments = [$event, offsets];
        $event.handleObj.handler.apply(this, displaceArguments);
      },
    };

    $.event.special.drupalViewportOffsetChange = eventSpecial;

    const listenEvent = (event) => {
      const docEvents = $._data(document, 'events');
      if (docEvents[event.type]) {
        docEvents[event.type].forEach((listener) => {
          Drupal.deprecationError({
            message: `jQuery event drupalViewportOffsetChange is deprecated in 10.3.0 and is removed from Drupal:11.1.0. See https://www.drupal.org/node/3449016`,
          });
        });
      }
    };
    document.addEventListener('drupalViewportOffsetChange', listenEvent);
  }
})(jQuery, Drupal, once);
