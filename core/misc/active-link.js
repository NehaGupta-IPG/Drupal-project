/**
 * @file
 * Attaches behaviors for Drupal's active link marking.
 */

(function (Drupal, drupalSettings) {
  /**
   * Builds the current query as a plain object from window.location.
   *
   * @return {Object}
   *   A map of query parameter names to values.
   */
  function getCurrentQuery() {
    const query = {};
    new URLSearchParams(window.location.search).forEach((value, key) => {
      query[key] = value;
    });
    return query;
  }

  /**
   * Determines whether a data-drupal-link-query attribute matches a query.
   *
   * The attribute value is parsed with JSON.parse() and compared value by
   * value against the current query.
   *
   * @param {string|null} attributeValue
   *   The data-drupal-link-query attribute value, or null if the link has
   *   no such attribute.
   * @param {Object} currentQuery
   *   The current query, as returned by getCurrentQuery().
   *
   * @return {boolean}
   *   Whether the attribute represents the same query parameters.
   */
  function queryMatches(attributeValue, currentQuery) {
    const currentKeys = Object.keys(currentQuery);
    if (attributeValue === null) {
      return currentKeys.length === 0;
    }
    let linkQuery;
    try {
      linkQuery = JSON.parse(attributeValue);
    } catch (error) {
      return false;
    }
    const linkKeys = Object.keys(linkQuery);
    return (
      linkKeys.length === currentKeys.length &&
      linkKeys.every((key) => linkQuery[key] === currentQuery[key])
    );
  }

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

      // The query is not part of the selectors above: matching it requires
      // comparing decoded values, so it is read from window.location.
      const currentQuery = getCurrentQuery();

      context.querySelectorAll(selectors.join(',')).forEach((activeLink) => {
        if (
          queryMatches(
            activeLink.getAttribute('data-drupal-link-query'),
            currentQuery,
          )
        ) {
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
