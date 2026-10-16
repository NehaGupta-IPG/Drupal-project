/**
 * @file
 * Define a <drupal-dialog> custom element.
 */

// cspell:ignore closedby

(function (Drupal, drupalSettings) {
  const supportsClosedBy =
    HTMLDialogElement.prototype.hasOwnProperty('closedBy');

  /*
   * Light DOM wrapper around a native <dialog>. Owns exactly:
   *   1. showModal() / show() on connectedCallback based on the `data-modal`
   *   attribute
   *   2. closedby="any" backdrop-click polyfill for Safari
   *   3. Self-removal from DOM on dialog close (unless data-persist)
   *   4. Cleanup when removed by other code via disconnectedCallback
   */
  class DrupalDialog extends HTMLElement {
    connectedCallback() {
      const waitForDialog = new Promise((resolve) => {
        const dialog = this.querySelector('dialog, #drupal-off-canvas-wrapper');
        if (dialog) {
          resolve(dialog);
        } else {
          const observer = new MutationObserver((mutations, observer) => {
            const element = this.querySelector(
              'dialog, #drupal-off-canvas-wrapper',
            );
            if (element) {
              observer.disconnect();
              resolve(element);
            }
          });

          observer.observe(document.body, {
            childList: true,
            subtree: true,
          });
        }
      });

      waitForDialog.then((dialog) => {
        // Backdrop polyfill for closedby="any" on Safari.
        if (!supportsClosedBy && dialog.getAttribute('closedby') === 'any') {
          this._backdropHandler = (e) => {
            if (e.target === dialog) dialog.close();
          };
          dialog.addEventListener('click', this._backdropHandler);
        } else if (dialog.id === 'drupal-off-canvas-wrapper') {
          // Initialize the off-canvas dialog.
          const closeButton = dialog.querySelector('.dialog__titlebar-close');
          if (closeButton) {
            closeButton.addEventListener('click', (event) => {
              event.preventDefault();
              const closeEvent = new Event('close', { bubbles: true });
              event.target.dispatchEvent(closeEvent);
            });
          }
        }

        // Remove self from DOM on close unless data-persist.
        let skipClose = false;
        dialog.addEventListener('close', () => {
          if (skipClose) {
            skipClose = false;
            return;
          }
          if (!this.hasAttribute('data-persist')) this.remove();
        });

        // Open the dialog — open attribute is the declarative signal from the server.
        // data-modal on <dialog> upgrades to showModal(); plain open = non-modal show().
        if (dialog.hasAttribute('open')) {
          skipClose = true;
          dialog.close();
          if (dialog.hasAttribute('data-modal') || !supportsClosedBy) {
            // Our current polyfill needs the entire screen to be a click target..
            dialog.showModal();
          } else {
            dialog.show();
          }
        }
      });
    }

    disconnectedCallback() {
      // This element was deleted without calling
      // dialog.close(). Clean up and close silently if still open.
      const dialog = this.querySelector('dialog');
      if (!dialog) return;
      if (this._backdropHandler)
        dialog.removeEventListener('click', this._backdropHandler);
      // Our htmx integration detaches behaviors, but since this is an SDC
      // we also need to call it as this may be used elsewhere.
      Drupal.detachBehaviors(this, drupalSettings, 'unload');
      if (dialog.open) dialog.close();
    }
  }

  customElements.define('drupal-dialog', DrupalDialog);
})(Drupal, drupalSettings);
