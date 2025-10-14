/**
 * @file
 * Show and hide the "Back to top" button if browser doesn't support native CSS
 * scrolling.
 */

(() => {
  // Exit if browser supports native CSS scrolling.
  if (CSS.supports('animation-timeline', 'scroll()')) return;

  const backToTop = document.querySelector('.back-to-top');

  const options = {
    rootMargin: '600px 0px 0px 0px',
    threshold: 0,
  };

  /**
   * Toggles visibility of the button.
   *
   * @param {Array} entries - List of IntersectionObserverEntry objects.
   */
  function toggleVisibility(entries) {
    backToTop.classList.toggle('is-visible', !entries[0].isIntersecting);
  }

  const observer = new IntersectionObserver(toggleVisibility, options);

  // We monitor the header because it's short, near the top of the page, and
  // always present.
  observer.observe(document.querySelector('.site-header'));
})();
