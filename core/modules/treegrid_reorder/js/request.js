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
 * The helper also keeps count of the writes it has running or waiting, which,
 * with the moves accepted and unfinished and the needs-reload state, decide
 * whether another move may start: one move at a time, and none while a write
 * such as Save is in flight. A refused gesture is announced once for as long as
 * the element stays busy.
 *
 * Exposes Drupal.treegridReorder.request and, as internals,
 * Drupal.treegridReorder.enterNeedsReload, needsReloadReason and the move
 * gate (isBusy, acceptMove, finishMove, announceRefusal).
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

  // Per element: moves accepted and unfinished, writes running or waiting, and
  // whether the current busy period's refusal has been announced.
  const activity = new WeakMap();

  /**
   * The activity record of an element.
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element.
   *
   * @return {{moves: number, writes: number, announced: boolean}}
   *   The record.
   */
  function activityOf(element) {
    if (!activity.has(element)) {
      activity.set(element, { moves: 0, writes: 0, announced: false });
    }
    return activity.get(element);
  }

  /**
   * Starts a new busy period's announcement once nothing is busy any more.
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element.
   */
  function resetAnnouncement(element) {
    const record = activityOf(element);
    if (record.moves === 0 && record.writes === 0) {
      record.announced = false;
    }
  }

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
   * Whether a new move must be refused.
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element.
   *
   * @return {boolean}
   *   True while a move is accepted and unfinished, a write is running or
   *   waiting, or the tree needs a reload.
   *
   * @internal
   */
  function isBusy(element) {
    const record = activityOf(element);
    return (
      record.moves > 0 ||
      record.writes > 0 ||
      needsReloadReason(element) !== null
    );
  }

  /**
   * Accepts a move if none is in the way, flagging the element as moving.
   *
   * The element carries data-treegrid-reorder-moving from here until
   * finishMove(), the time the move spends queued included.
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element.
   *
   * @return {boolean}
   *   False when the move must be refused.
   *
   * @internal
   */
  function acceptMove(element) {
    if (isBusy(element)) {
      return false;
    }
    activityOf(element).moves += 1;
    element.setAttribute('data-treegrid-reorder-moving', 'true');
    return true;
  }

  /**
   * Ends an accepted move.
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element.
   *
   * @internal
   */
  function finishMove(element) {
    const record = activityOf(element);
    record.moves -= 1;
    if (record.moves === 0) {
      element.removeAttribute('data-treegrid-reorder-moving');
    }
    resetAnnouncement(element);
  }

  /**
   * Announces a refused gesture, once for as long as the element stays busy.
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element.
   *
   * @internal
   */
  function announceRefusal(element) {
    const record = activityOf(element);
    if (record.announced) {
      return;
    }
    record.announced = true;
    Drupal.treegrid.announce(
      needsReloadReason(element) !== null
        ? Drupal.t('Reload the page before moving more items.')
        : Drupal.t(
            'A change is still being saved. Try again once it has finished.',
          ),
      'assertive',
    );
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
    } catch {
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
    const upperMethod = method.toUpperCase();
    const run = () => send(element, url, upperMethod, body);
    if (!WRITE_METHODS.includes(upperMethod)) {
      return unit ? run() : element.exclusive(run);
    }
    const record = activityOf(element);
    record.writes += 1;
    const finished = () => {
      record.writes -= 1;
      resetAnnouncement(element);
    };
    const result = unit ? run() : element.exclusive(run);
    result.then(finished, finished);
    return result;
  };

  Drupal.treegridReorder.needsReloadReason = needsReloadReason;
  Drupal.treegridReorder.enterNeedsReload = enterNeedsReload;
  Drupal.treegridReorder.isBusy = isBusy;
  Drupal.treegridReorder.acceptMove = acceptMove;
  Drupal.treegridReorder.finishMove = finishMove;
  Drupal.treegridReorder.announceRefusal = announceRefusal;
})(Drupal);
