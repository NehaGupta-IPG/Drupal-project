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
      // JSON encode an object.
      const jsonEncode = (object, stringify) => {
        object = structuredClone(object);
        const entries = Object.entries(object);

        for (let i = 0; i < entries.length; i += 1) {
          const [key, value] = entries[i];

          if (typeof value === 'string') {
            object[key] = value.replace(
              /[\u007F-\uFFFF\u003c\u003e\u0022\u0027\u0026]/g,
              (chr) => {
                return '&u' + ('0000' + chr.charCodeAt(0).toString(16)).substr(-4);
              }
            );
          }
          else if (typeof value === 'object') {
            object[key] = jsonEncode(value, false);
          }
        }

        if (stringify === false) {
          return object;
        }

        return JSON.stringify(object).replace('&u', '\\u');
      };

      // Start by finding all potentially active links.
      const path = drupalSettings.path;
      const originalSelectors = [
        `[data-drupal-link-system-path="${CSS.escape(path.currentPath)}"]`,
      ];

      // If this is the front page, we have to check for the <front> path as well.
      if (path.isFront) {
        originalSelectors.push('[data-drupal-link-system-path="<front>"]');
      }

      // Add language filtering.
      let selectors = [].concat(
        // Links without any hreflang attributes (most of them).
        originalSelectors.map((selector) => `${selector}:not([hreflang])`),
        // Links with hreflang equals to the current language.
        originalSelectors.map(
          (selector) => `${selector}[hreflang="${path.currentLanguage}"]`,
        ),
      );

      // Create selectors with and without query parameters to support views.
      const querySelector = path.currentQuery
        ? `[data-drupal-link-query="${CSS.escape(jsonEncode(path.currentQuery))}"]`
        : ':not([data-drupal-link-query])';
      const allSelectors = selectors.concat(selectors.map((current) => current + querySelector));

      // Query the DOM for all matching links.
      const activeLinks = context.querySelectorAll(allSelectors.join(','));
      const il = activeLinks.length;
      for (let i = 0; i < il; i++) {
        activeLinks[i].classList.add('is-active');
        activeLinks[i].setAttribute('aria-current', 'page');
      }
    },
    detach(context, settings, trigger) {
      if (trigger === 'unload') {
        const activeLinks = context.querySelectorAll(
          '[data-drupal-link-system-path].is-active',
        );
        const il = activeLinks.length;
        for (let i = 0; i < il; i++) {
          activeLinks[i].classList.remove('is-active');
          activeLinks[i].removeAttribute('aria-current');
        }
      }
    },
  };
})(Drupal, drupalSettings);
