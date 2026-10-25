/**
 * @file
 * Save / Cancel wiring for the menu tree working copy.
 *
 * Moves are recorded in a per-user server-side working copy instead of being
 * written immediately, so an editor can make many changes and then keep or
 * discard them. This posts to the save/cancel routes and reveals the "unsaved
 * changes" notice after the first recorded change.
 *
 * It is a Drupal.behaviors attach rather than part of the <drupal-treegrid> custom
 * element because the action bar lives in the page template, outside the
 * element. It listens for the menu-tree-ui:changed event the move commit
 * dispatches, keeping the two decoupled.
 */

((Drupal, drupalSettings, once) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};

  // Bound once for the page lifetime (see attach). A per-bar document listener
  // would accumulate: once() only guards re-wiring the same bar element, so an
  // htmx swap that injects a new action bar would add another permanent listener
  // and retain the detached one's closure.
  let changedListenerBound = false;

  /**
   * Reveals the unsaved-changes notice and clears save errors on a change.
   *
   * Queries the elements fresh rather than closing over them, so a single
   * listener serves whatever action bar is currently in the DOM.
   */
  function handleChanged() {
    document
      .querySelectorAll('.menu-tree-ui__unsaved')
      .forEach((notice) => notice.classList.remove('js-hide'));
    document
      .querySelectorAll('.menu-tree-ui__save-error')
      .forEach((container) => new Drupal.Message(container).clear());
  }

  /**
   * Wires one action bar's Save / Cancel buttons and unsaved notice.
   *
   * @param {HTMLElement} bar
   *   The .menu-tree-ui__actions element.
   */
  function setupActions(bar) {
    const settings = drupalSettings.menuTreeUi ?? {};
    // The notice and the error container sit in the action bar beside these
    // buttons; scope the lookups to the whole region so they are found wherever
    // in the bar they render.
    const region = bar.closest('.menu-tree-ui');
    const element = region?.querySelector('drupal-treegrid');
    const notice = region?.querySelector('.menu-tree-ui__unsaved');
    const errorContainer = region?.querySelector('.menu-tree-ui__save-error');
    const messages = errorContainer ? new Drupal.Message(errorContainer) : null;
    const save = bar.querySelector('[data-mtu-action="save"]');
    const cancel = bar.querySelector('[data-mtu-action="cancel"]');
    let inFlight = false;

    /**
     * Shows or hides the unsaved notice from a response's dirty flag.
     *
     * @param {boolean|undefined} dirty
     *   Whether the working copy still holds changes. A response that does not
     *   say leaves the notice as it is.
     */
    function setUnsaved(dirty) {
      if (typeof dirty === 'boolean') {
        notice?.classList.toggle('js-hide', !dirty);
      }
    }

    /**
     * Posts to a working-copy endpoint, one request at a time.
     *
     * aria-disabled rather than disabled keeps the buttons focusable, so
     * keyboard focus is not lost while the request runs.
     *
     * @param {string|undefined} url
     *   The save or cancel URL.
     * @param {string} failureMessage
     *   Shown when the response carries no message of its own.
     * @param {Function} onSuccess
     *   Called when the server reports success.
     */
    async function submit(url, failureMessage, onSuccess) {
      if (!url || !element || inFlight) {
        return;
      }
      inFlight = true;
      [save, cancel].forEach((button) =>
        button?.setAttribute('aria-disabled', 'true'),
      );
      messages?.clear();
      try {
        const result = await Drupal.treegridReorder.request(element, url);
        if (result.ok) {
          setUnsaved(result.body.dirty);
          onSuccess();
        } else {
          messages?.add(result.message ?? failureMessage, { type: 'error' });
        }
      } finally {
        inFlight = false;
        [save, cancel].forEach((button) =>
          button?.removeAttribute('aria-disabled'),
        );
      }
    }

    save?.addEventListener('click', () =>
      submit(settings.saveUrl, Drupal.t('Save failed.'), () => {
        // The tree already shows the saved order (it was rendering the working
        // copy, which is now committed), so there is nothing to re-fetch. Don't
        // reload: that would collapse every expanded section and lose scroll
        // position on large menus.
        Drupal.treegrid.announce(Drupal.t('Changes saved.'), 'polite');
      }),
    );

    cancel?.addEventListener('click', () =>
      submit(settings.cancelUrl, Drupal.t('Cancel failed.'), () =>
        window.location.reload(),
      ),
    );
  }

  Drupal.behaviors.menuTreeUiWorkingCopy = {
    attach(context) {
      if (!changedListenerBound) {
        document.addEventListener('menu-tree-ui:changed', handleChanged);
        changedListenerBound = true;
      }
      once('mtu-working-copy', '.menu-tree-ui__actions', context).forEach(
        setupActions,
      );
    },
  };
})(Drupal, drupalSettings, once);
