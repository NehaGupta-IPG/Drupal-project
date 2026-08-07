/**
 * @file
 * Attaches behaviors for Drupal's active link marking.
 */

(function (Drupal, drupalSettings) {
  /**
   * Append is-active class.
   *
   * The link is only active if its path corresponds to the current path, the
   * language of the linked path is equal to the current language, and if the
   * query parameters of the link equal those of the current request, since the
   * same request with different query parameters may yield a different page
   * (e.g. pagers, exposed View filters).
   *
   * Does not discriminate based on element type, so allows you to set the
   * is-active class on any element: a, li…
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.activeLinks = {
    attach(context) {
      // Start by finding all potentially active links.
      const path = drupalSettings.path;
      path.currentQuery = path.currentQuery ?? {};
      const originalSelectors = [
        `[data-drupal-link-system-path="${CSS.escape(path.currentPath)}"]`,
      ];

      // If this is the front page, we have to check for the <front> path as
      // well.
      if (path.isFront) {
        originalSelectors.push('[data-drupal-link-system-path="<front>"]');
      }

      // Add language filtering.
      const selectors = [].concat(
        // Links without any hreflang attributes (most of them).
        originalSelectors.map(
          (selector) =>
            `${selector}:not([data-drupal-language]):not([hreflang])`,
        ),
        // Links li with data-drupal-language equals to the current language.
        originalSelectors.map(
          (selector) =>
            `li${selector}[data-drupal-language="${path.currentLanguage}"]`,
        ),
        // Links a with hreflang equals to the current language.
        originalSelectors.map(
          (selector) => `a${selector}[hreflang="${path.currentLanguage}"]`,
        ),
      );

      context.querySelectorAll(selectors.join(',')).forEach((activeLink) => {
        // Check if the link does not contain query parameters that
        // don't match the current query.
        const queryMatch =
          !activeLink.hasAttribute('data-drupal-link-query') ||
          !Object.entries(
            JSON.parse(activeLink.getAttribute('data-drupal-link-query')),
          ).some(
            ([key, value]) =>
              JSON.stringify(value) !==
              JSON.stringify(path.currentQuery[key] ?? null),
          );

        if (queryMatch) {
          activeLink.classList.add('is-active');
          activeLink.setAttribute('aria-current', 'page');
        }
      });
    },
    detach(context, settings, trigger) {
      if (trigger === 'unload') {
        context
          .querySelectorAll('[data-drupal-link-system-path].is-active')
          .forEach((activeLink) => {
            activeLink.classList.remove('is-active');
            activeLink.removeAttribute('aria-current');
          });
      }
    },
  };
})(Drupal, drupalSettings);
