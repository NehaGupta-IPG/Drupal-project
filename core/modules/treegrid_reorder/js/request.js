/**
 * @file
 * The request helper for writes from a reorder tree, and the needs-reload
 * state.
 *
 * Drupal.treegridReorder.request() sends a JSON request that carries the CSRF
 * token and the working copy's revision, runs through the element's queue, and
 * resolves a uniform result. The revision lives in the element's
 * data-treegrid-reorder-revision attribute: the helper sends it in the
 * X-Treegrid-Reorder-Revision header and writes back the header of any
 * response that carries one.
 *
 * A 409 'stale' response puts the tree into the needs-reload state: its
 * rendering may no longer match the server, so every later write through the
 * helper resolves unsent and moves are refused. Reads still go out.
 *
 * Exposes Drupal.treegridReorder.request and, as internals,
 * Drupal.treegridReorder.enterNeedsReload and needsReloadReason.
 */

((Drupal) => {
  Drupal.treegridReorder = Drupal.treegridReorder || {};

  const REVISION_HEADER = 'X-Treegrid-Reorder-Revision';
  const REVISION_ATTRIBUTE = 'data-treegrid-reorder-revision';
  const RELOAD_ATTRIBUTE = 'data-treegrid-reorder-needs-reload';
  const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

  // How strict each needs-reload reason is: the state only gets stricter, so a
  // reason replaces another only when it ranks higher.
  const REASON_RANK = {
    'refresh-failed': 1,
    'pending-revisions': 1,
    stale: 2,
  };

  /**
   * The reason a tree needs a reload, if it does.
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element.
   *
   * @return {?string}
   *   'stale', 'refresh-failed' or 'pending-revisions', or null.
   *
   * @internal
   */
  function needsReloadReason(element) {
    return element.getAttribute(RELOAD_ATTRIBUTE);
  }

  /**
   * Puts a tree into the needs-reload state, or makes the state stricter.
   *
   * Fires treegrid-reorder:needs-reload when the state is entered or a stricter
   * reason replaces the current one; a reason that is not stricter changes
   * nothing.
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element.
   * @param {string} reason
   *   'stale', 'refresh-failed' or 'pending-revisions'.
   *
   * @internal
   */
  function enterNeedsReload(element, reason) {
    const current = needsReloadReason(element);
    if (current !== null && REASON_RANK[reason] <= REASON_RANK[current]) {
      return;
    }
    element.setAttribute(RELOAD_ATTRIBUTE, reason);
    element.dispatchEvent(
      new CustomEvent('treegrid-reorder:needs-reload', {
        bubbles: true,
        detail: { reason },
      }),
    );
  }

  /**
   * Sends one request and builds its result.
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element.
   * @param {string} url
   *   The endpoint.
   * @param {string} method
   *   The upper-case HTTP method.
   * @param {*} body
   *   The value to send as JSON, or undefined for no body.
   *
   * @return {Promise<object>}
   *   The result: { ok, status, code, message, body, sent }.
   */
  async function send(element, url, method, body) {
    const refused = {
      ok: false,
      status: 0,
      code: 'stale',
      message: null,
      body: null,
      sent: false,
    };
    const isWrite = WRITE_METHODS.includes(method);
    if (isWrite && needsReloadReason(element) === 'stale') {
      return refused;
    }

    const headers = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    };
    const token = await Drupal.treegrid.csrfToken(element);
    if (token !== '') {
      headers['X-CSRF-Token'] = token;
    }
    if (element.hasAttribute(REVISION_ATTRIBUTE)) {
      headers[REVISION_HEADER] = element.getAttribute(REVISION_ATTRIBUTE);
    }
    // The token may have taken a moment: another write can have found the tree
    // stale in the meantime.
    if (isWrite && needsReloadReason(element) === 'stale') {
      return refused;
    }

    let response;
    try {
      response = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
        body: body === undefined ? undefined : JSON.stringify(body),
      });
    } catch (e) {
      return {
        ok: false,
        status: 0,
        code: null,
        message: null,
        body: null,
        sent: true,
      };
    }

    const revision = response.headers.get(REVISION_HEADER);
    if (revision !== null) {
      element.setAttribute(REVISION_ATTRIBUTE, revision);
    }
    const data = await response.json().catch(() => null);
    const hasFields = data !== null && typeof data === 'object';
    const code = hasFields ? (data.code ?? null) : null;
    if (response.status === 409 && code === 'stale') {
      enterNeedsReload(element, 'stale');
    }
    return {
      ok: response.ok && hasFields && data.ok === true,
      status: response.status,
      code,
      message: hasFields ? (data.message ?? null) : null,
      body: data,
      sent: true,
    };
  }

  /**
   * Sends a JSON request from a reorder tree.
   *
   * Without a unit the request is its own unit in the element's queue; with the
   * unit a running move passes, it runs inside that unit.
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element.
   * @param {string} url
   *   The endpoint.
   * @param {object} [options]
   *   Options.
   * @param {string} [options.method]
   *   The HTTP method. POST, PUT, PATCH and DELETE are writes; GET is a read.
   * @param {*} [options.body]
   *   The value to send as JSON.
   * @param {object} [options.unit]
   *   The unit the request runs inside.
   *
   * @return {Promise<{ok: boolean, status: number, code: ?string, message: ?string, body: *, sent: boolean}>}
   *   ok is true only for a 2xx response whose JSON body has ok: true. status
   *   is 0 when nothing came back; sent says whether a request left the
   *   browser.
   */
  Drupal.treegridReorder.request = (
    element,
    url,
    { method = 'POST', body, unit } = {},
  ) => {
    const run = () => send(element, url, method.toUpperCase(), body);
    return unit ? run() : element.exclusive(run);
  };

  Drupal.treegridReorder.needsReloadReason = needsReloadReason;
  Drupal.treegridReorder.enterNeedsReload = enterNeedsReload;
})(Drupal);
