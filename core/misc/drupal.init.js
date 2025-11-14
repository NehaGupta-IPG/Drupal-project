// Allow other JavaScript libraries to use $.
if (window.jQuery) {
  jQuery.noConflict();
}

// Class indicating that JS is enabled; used for styling purpose.
document.documentElement.className += ' js';

// JavaScript should be made compatible with libraries other than jQuery by
// wrapping it in an anonymous closure.
(function (Drupal, drupalSettings) {
  /**
   * Calls callback when document ready.
   *
   * @param {function} callback
   *   The function to be called on document ready.
   */
  const domReady = (callback) => {
    const listener = () => {
      callback();
      document.removeEventListener('DOMContentLoaded', listener);
    };
    if (document.readyState !== 'loading') {
      setTimeout(callback, 0);
    } else {
      document.addEventListener('DOMContentLoaded', listener);
    }
  };

  // Attach all behaviors.
  domReady(() => {
    // Initialize the attached behaviors tracking
    if (!Drupal._attachedBehaviors) {
      Drupal._attachedBehaviors = new WeakSet();
    }

    // Mark all existing behaviors as attached
    Object.keys(Drupal.behaviors || {}).forEach((key) => {
      const behavior = Drupal.behaviors[key];
      if (behavior && typeof behavior.attach === 'function') {
        Drupal._attachedBehaviors.add(behavior);
      }
    });

    // Call attachBehaviors
    Drupal.attachBehaviors(document, drupalSettings);
  });
})(Drupal, window.drupalSettings);
