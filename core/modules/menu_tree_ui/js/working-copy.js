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
 * element. It listens for the treegrid-reorder:moved event, which bubbles to
 * the document, keeping the two decoupled. A move's response says whether the
 * working copy holds changes (dirty), and so do Save and Cancel's.
 */

((Drupal, drupalSettings, once) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};

  // Bound once for the page lifetime (see attach). A per-bar document listener
  // would accumulate: once() only guards re-wiring the same bar element, so an
  // htmx swap that injects a new action bar would add another permanent listener
  // and retain the detached one's closure.
  let movedListenerBound = false;

  /**
   * Shows the needs-reload message and its Reload button.
   *
   * The message names the reason. Focus moves to the Reload button only if it
   * was in the tree (or nowhere, because a refresh removed the focused row);
   * otherwise the message is announced politely.
   *
   * @param {CustomEvent} event
   *   The treegrid-reorder:needs-reload event.
   */
  function handleNeedsReload(event) {
    const element = event.target;
    const region = element.closest('.menu-tree-ui');
    const area = region?.querySelector('[data-mtu-needs-reload]');
    if (!area) {
      return;
    }
    const messages = {
      stale: Drupal.t(
        'Another editing session changed this menu, so changes can no longer be made from this page. Reload the page to continue.',
      ),
      'refresh-failed': Drupal.t(
        'Your last move was saved, but the tree could not be redrawn. Reload the page to see the current order.',
      ),
    };
    const message = messages[event.detail.reason] ?? '';
    area.querySelector('[data-mtu-needs-reload-message]').textContent = message;
    area.hidden = false;
    const active = document.activeElement;
    if (active === document.body || element.tree?.contains(active)) {
      // The button's description (the message) is spoken with the focus.
      area.querySelector('[data-mtu-action="reload"]').focus();
    } else {
      Drupal.treegrid.announce(message, 'polite');
    }
  }

  /**
   * Reveals the unsaved-changes notice and clears save errors after a move.
   *
   * Only a move whose response says the working copy holds changes counts.
   * Queries the elements fresh rather than closing over them, so a single
   * listener serves whatever action bar is currently in the DOM.
   *
   * @param {CustomEvent} event
   *   The treegrid-reorder:moved event.
   */
  function handleMoved(event) {
    if (event.detail.result?.body?.dirty !== true) {
      return;
    }
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
    region
      ?.querySelector('[data-mtu-action="reload"]')
      ?.addEventListener('click', () => window.location.reload());
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
      if (!movedListenerBound) {
        document.addEventListener('treegrid-reorder:moved', handleMoved);
        document.addEventListener(
          'treegrid-reorder:needs-reload',
          handleNeedsReload,
        );
        movedListenerBound = true;
      }
      once('mtu-working-copy', '.menu-tree-ui__actions', context).forEach(
        setupActions,
      );
    },
  };
})(Drupal, drupalSettings, once);
