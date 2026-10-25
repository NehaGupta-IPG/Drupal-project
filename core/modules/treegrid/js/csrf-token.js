/**
 * @file
 * Reads the CSRF token that routes requiring the X-CSRF-Token header check.
 *
 * The token comes from the element's data-treegrid-csrf-token attribute when
 * the consumer rendered one (an empty value means no token is needed, as for an
 * anonymous user). Without the attribute, a logged-in user's token is fetched
 * from /session/token once per page. An anonymous visitor never fetches: the
 * request would start a session for them and the header check does not apply to
 * them.
 *
 * Exposes Drupal.treegrid.csrfToken.
 */

((Drupal, drupalSettings) => {
  Drupal.treegrid = Drupal.treegrid || {};

  const ATTRIBUTE = 'data-treegrid-csrf-token';
  let cachedToken = null;
  let pendingFetch = null;

  /**
   * Fetches the session's token, sharing one request between callers.
   *
   * A success is cached for the page. A failure resolves to an empty string and
   * is not cached, so the next caller tries again.
   *
   * @return {Promise<string>}
   *   The token, or '' when it could not be fetched.
   */
  function fetchSessionToken() {
    if (cachedToken !== null) {
      return Promise.resolve(cachedToken);
    }
    if (pendingFetch === null) {
      pendingFetch = fetch(Drupal.url('session/token'), {
        credentials: 'same-origin',
      })
        .then((response) => {
          if (!response.ok) {
            return '';
          }
          return response.text().then((token) => {
            cachedToken = token;
            return token;
          });
        })
        .catch(() => '')
        .finally(() => {
          pendingFetch = null;
        });
    }
    return pendingFetch;
  }

  /**
   * Resolves the CSRF token to send in the X-CSRF-Token header.
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element.
   *
   * @return {Promise<string>}
   *   The token. An empty string means no header is to be sent.
   */
  Drupal.treegrid.csrfToken = (element) => {
    if (element.hasAttribute(ATTRIBUTE)) {
      return Promise.resolve(element.getAttribute(ATTRIBUTE));
    }
    if (String(drupalSettings.user?.uid) === '0') {
      return Promise.resolve('');
    }
    return fetchSessionToken();
  };
})(Drupal, drupalSettings);
