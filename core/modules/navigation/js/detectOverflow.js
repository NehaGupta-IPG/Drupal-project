/**
 * Observes child element resize and apply attributes when element has overflow.
 *
 * Can be reused and expanded as separated library.
 *
 * @type {Drupal~behavior}
 *
 * @prop {Drupal~behaviorAttach} attach
 *   Attaches the overflow detection for elements.
 */
(
  (Drupal, once, debounce) => {
    Drupal.behaviors.detectOverflow = {
      attach: (context) => {
        const yOverflow = (element) =>
          element.scrollHeight > element.clientHeight;
        const xOverflow = (element) =>
          element.scrollWidth > element.clientWidth;
        const setAttributes = (element) => {
          const scrollableNav = element.querySelector(
            '.admin-toolbar__content',
          );
          if (window.matchMedia('(min-width: 64rem)').matches) {
            element.setAttribute(
              'data-detected-y-overflow',
              yOverflow(scrollableNav),
            );
          } else {
            element.setAttribute(
              'data-detected-y-overflow',
              yOverflow(element),
            );
          }
          element.setAttribute('data-detected-x-overflow', xOverflow(element));
        };
        const elements = once(
          'detectOverflow',
          context.querySelectorAll('[data-detect-overflow]'),
        );
        const resizeObserver = new ResizeObserver(
          debounce((entries) => {
            entries.forEach((entry) => {
              setAttributes(entry.target.parentNode);
            });
          }, 100),
        );
        elements.forEach((element) => {
          if (element.children) {
            Array.from(element.children).forEach((child) => {
              resizeObserver.observe(child);
            });
          }
        });
      },
    };
  }
)(Drupal, once, Drupal.debounce);
