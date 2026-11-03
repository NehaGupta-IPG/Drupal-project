/**
 * @file
 * Attaches behaviors for Drupal's active link marking.
 */

(function (Drupal, drupalSettings) {
  /**
   * Determines whether a link query value matches a current query value.
   *
   * Scalars are compared as strings, null matches a missing parameter, and
   * arrays and objects must have the same keys with matching values.
   *
   * @param {*} linkValue
   *   The value from the link query.
   * @param {*} currentValue
   *   The value from the current query, or undefined if not present.
   *
   * @return {boolean}
   *   True if the values match.
   *
   * @see \Drupal\Core\EventSubscriber\ActiveLinkResponseFilter::isQuerySubset()
   */
  function isQueryValueMatch(linkValue, currentValue) {
    const isMissing = (value) => value === null || value === undefined;
    if (isMissing(linkValue)) {
      return isMissing(currentValue);
    }
    if (typeof linkValue === 'object') {
      return (
        !isMissing(currentValue) &&
        typeof currentValue === 'object' &&
        Object.keys(linkValue).length === Object.keys(currentValue).length &&
        Object.keys(linkValue).every((key) =>
          isQueryValueMatch(linkValue[key], currentValue[key]),
        )
      );
    }
    // PHP casts booleans to '1' and '0' when building a query string.
    const toString = (value) =>
      typeof value === 'boolean' ? String(Number(value)) : String(value);
    return (
      !isMissing(currentValue) &&
      typeof currentValue !== 'object' &&
      toString(linkValue) === toString(currentValue)
    );
  }

  /**
   * Determines whether the query of a link is a subset of the current query.
   *
   * @param {Element} link
   *   The element with the data-drupal-link-query attribute.
   * @param {Object} currentQuery
   *   The query of the current request.
   *
   * @return {boolean}
   *   True if the element has no link query, or all of its parameters match.
   *   False if the attribute cannot be parsed.
   *
   * @see \Drupal\Core\EventSubscriber\ActiveLinkResponseFilter::isQuerySubset()
   */
  function isQuerySubset(link, currentQuery) {
    if (!link.hasAttribute('data-drupal-link-query')) {
      return true;
    }
    try {
      const linkQuery = JSON.parse(link.getAttribute('data-drupal-link-query'));
      return Object.keys(linkQuery).every((key) =>
        isQueryValueMatch(linkQuery[key], currentQuery[key]),
      );
    } catch (e) {
      return false;
    }
  }

  /**
   * Append is-active class.
   *
   * The link is only active if its path corresponds to the current path, the
   * language of the linked path is equal to the current language, and if the
   * query parameters of the link are all present in the current request. The
   * current request may have additional parameters, such as a pager.
   *
   * Does not discriminate based on element type, so allows you to set the
   * is-active class on any element: a, li…
   *
   * The matching rules must be kept in sync with the server-side
   * implementation, which is used for anonymous users.
   *
   * @type {Drupal~behavior}
   *
   * @see \Drupal\Core\EventSubscriber\ActiveLinkResponseFilter
   */
  Drupal.behaviors.activeLinks = {
    attach(context) {
      // Start by finding all potentially active links.
      const path = drupalSettings.path;
      const currentQuery = path.currentQuery ?? {};
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
        if (isQuerySubset(activeLink, currentQuery)) {
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
