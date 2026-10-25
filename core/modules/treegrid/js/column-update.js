/**
 * @file
 * The column-update client: persists a checkbox cell's change.
 *
 * When the <drupal-treegrid> carries data-treegrid-column-update-url, a change
 * to an interactive checkbox cell (.treegrid-cell-checkbox) inside the tree is
 * POSTed there as { column_id, row_id, value } with the CSRF token. The request
 * goes out at once, outside the element's queue and with no revision. The
 * checkbox is disabled while the request is in flight. On failure (a non-2xx
 * status, ok: false, or a network error) the checkbox goes back to the value it
 * had before the change and the failure is announced.
 *
 * The response is { ok, code?, message? }. Success and failure are reported as
 * treegrid:cell-updated and treegrid:cell-update-failed events on the element,
 * with detail { rowId, columnId, value, status, code, message }.
 *
 * Exposes Drupal.treegrid.attachColumnUpdates (internal).
 */

((Drupal) => {
  Drupal.treegrid = Drupal.treegrid || {};
  const { rowOf } = Drupal.treegrid.treeNav;

  const CHECKBOX = 'input.treegrid-cell-checkbox';

  /**
   * Sends one cell change to the column-update endpoint.
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element.
   * @param {string} url
   *   The column-update endpoint.
   * @param {object} body
   *   The request body: { column_id, row_id, value }.
   *
   * @return {Promise<{ok: boolean, status: number, code: ?string, message: ?string}>}
   *   The outcome.
   */
  async function send(element, url, body) {
    const headers = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    };
    try {
      const token = await Drupal.treegrid.csrfToken(element);
      if (token !== '') {
        headers['X-CSRF-Token'] = token;
      }
      const response = await fetch(url, {
        method: 'POST',
        headers,
        credentials: 'same-origin',
        body: JSON.stringify(body),
      });
      const data = await response.json().catch(() => null);
      return {
        ok: response.ok && data?.ok === true,
        status: response.status,
        code: data?.code ?? null,
        message: data?.message ?? null,
      };
    } catch (e) {
      return { ok: false, status: 0, code: null, message: null };
    }
  }

  /**
   * Persists a checkbox change, reverting the checkbox if it is refused.
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element.
   * @param {HTMLInputElement} checkbox
   *   The changed checkbox.
   * @param {string} url
   *   The column-update endpoint.
   */
  async function update(element, checkbox, url) {
    const columnId = checkbox.dataset.columnId ?? '';
    const rowId = rowOf(checkbox)?.dataset.id ?? '';
    if (columnId === '' || rowId === '') {
      return;
    }
    const value = checkbox.checked;
    const hadFocus = document.activeElement === checkbox;
    checkbox.disabled = true;

    const result = await send(element, url, {
      column_id: columnId,
      row_id: rowId,
      value,
    });

    checkbox.disabled = false;
    if (hadFocus) {
      checkbox.focus();
    }
    const detail = { rowId, columnId, value, ...result };
    delete detail.ok;
    if (result.ok) {
      element.dispatchEvent(
        new CustomEvent('treegrid:cell-updated', { bubbles: true, detail }),
      );
      return;
    }
    checkbox.checked = !value;
    Drupal.treegrid.announce(
      result.message ?? Drupal.t('Update failed.'),
      'assertive',
    );
    element.dispatchEvent(
      new CustomEvent('treegrid:cell-update-failed', { bubbles: true, detail }),
    );
  }

  /**
   * Starts listening for checkbox changes inside an element's tree.
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element.
   *
   * @return {function(): void}
   *   Stops listening.
   *
   * @internal
   */
  Drupal.treegrid.attachColumnUpdates = (element) => {
    const { tree } = element;
    const onChange = (event) => {
      const checkbox = event.target;
      const url = element.dataset.treegridColumnUpdateUrl;
      if (
        !url ||
        !(checkbox instanceof Element) ||
        !checkbox.matches(CHECKBOX)
      ) {
        return;
      }
      update(element, checkbox, url);
    };
    tree.addEventListener('change', onChange);
    return () => tree.removeEventListener('change', onChange);
  };
})(Drupal);
