/**
 * @file
 * Save / Cancel wiring for the menu tree working copy.
 *
 * Moves are recorded in a per-user server-side working copy instead of being
 * written immediately, so an editor can make many changes and then keep or
 * discard them. This posts to the save/cancel routes and reveals the "unsaved
 * changes" notice after the first recorded change.
 *
 * It is a Drupal.behaviors attach rather than part of the <menu-tree> custom
 * element because the action bar lives in the page template, outside the
 * element. It listens for the menu-tree-ui:changed event the move commit
 * dispatches, keeping the two decoupled.
 */

((Drupal, drupalSettings, once) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};

  let csrfToken;

  /**
   * Fetches (and caches) the CSRF token for the working-copy POSTs.
   *
   * @return {Promise<string>}
   *   The token, or '' when it could not be fetched.
   */
  async function getToken() {
    if (csrfToken !== undefined) {
      return csrfToken;
    }
    const base = drupalSettings.path?.baseUrl ?? '/';
    try {
      const response = await fetch(`${base}session/token`, {
        credentials: 'same-origin',
      });
      csrfToken = await response.text();
    } catch (e) {
      csrfToken = '';
    }
    return csrfToken;
  }

  /**
   * POSTs to a working-copy endpoint with the CSRF header.
   *
   * @param {string} url
   *   The save or cancel URL.
   *
   * @return {Promise<object>}
   *   The decoded JSON response, or an error-shaped object.
   */
  async function post(url) {
    const token = await getToken();
    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-Token': token ?? '' },
        credentials: 'same-origin',
      });
      return await response.json().catch(() => ({ ok: false }));
    } catch (e) {
      return { ok: false };
    }
  }

  /**
   * Wires one action bar's Save / Cancel buttons and unsaved notice.
   *
   * @param {HTMLElement} bar
   *   The .menu-tree-ui__actions element.
   */
  function setupActions(bar) {
    const settings = drupalSettings.menuTreeUi ?? {};
    // The unsaved notice sits in the action bar beside these buttons; scope the
    // lookup to the whole region so it is found wherever in the bar it renders.
    const notice = bar
      .closest('.menu-tree-ui')
      ?.querySelector('.menu-tree-ui__unsaved');
    const save = bar.querySelector('[data-mtu-action="save"]');
    const cancel = bar.querySelector('[data-mtu-action="cancel"]');

    document.addEventListener('menu-tree-ui:changed', () => {
      notice?.classList.remove('js-hide');
    });

    save?.addEventListener('click', async () => {
      if (!settings.saveUrl) {
        return;
      }
      const result = await post(settings.saveUrl);
      if (result.ok) {
        // The tree already shows the saved order (it was rendering the working
        // copy, which is now committed), so there is nothing to re-fetch. Don't
        // reload — that would collapse every expanded section and lose scroll
        // position on large menus. Just retire the unsaved notice.
        notice?.classList.add('js-hide');
        if (Drupal.announce) {
          Drupal.announce(Drupal.t('Changes saved.'), 'polite');
        }
      } else if (Drupal.announce) {
        Drupal.announce(
          result.message ?? Drupal.t('Save failed.'),
          'assertive',
        );
      }
    });

    cancel?.addEventListener('click', async () => {
      if (!settings.cancelUrl) {
        return;
      }
      const result = await post(settings.cancelUrl);
      if (result.ok) {
        window.location.reload();
      }
    });
  }

  Drupal.behaviors.menuTreeUiWorkingCopy = {
    attach(context) {
      once('mtu-working-copy', '.menu-tree-ui__actions', context).forEach(
        setupActions,
      );
    },
  };
})(Drupal, drupalSettings, once);
