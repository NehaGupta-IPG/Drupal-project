/**
 * @file
 * Define a <drupal-dialog> custom element.
 */

// cspell:ignore closedby

(function (Drupal, drupalSettings, displace) {
  // True if this browser supports the closedBy property.
  const supportsClosedBy =
    HTMLDialogElement.prototype.hasOwnProperty('closedBy');

  /**
   * Light DOM wrapper around a native <dialog>. Owns exactly:
   *   1. showModal() / show() on connectedCallback based on the `data-modal`
   *   attribute
   *   2. Off-canvas dialog initialization and displacement.
   *   3. closedby="any" backdrop-click polyfill for Safari
   *   4. Self-removal from DOM on dialog close (unless data-persist)
   *   5. Cleanup when removed by other code via disconnectedCallback
   */
  class DrupalDialog extends HTMLElement {
    adjustForDisplacement(dialog, removed = false) {
      const dimensions = dialog.getBoundingClientRect();
      const position = dialog.classList.contains('dialog--position-top')
        ? 'top'
        : 'side';
      if (position === 'top') {
        // No specific adjustment needed for top-positioned dialogs.
        // This is swapped into the DOM before the main canvas.
        return;
      }
      // Add the attributes needed by Drupal.displace.
      let edge = document.documentElement.dir === 'rtl' ? 'left' : 'right';
      const offset = removed ? 0 : dimensions.width;
      dialog.setAttribute(`data-offset-${edge}`, offset);
      // Signal the displacement and calculate the CSS variables used
      // in our style sheets.
      displace();
    }

    connectedCallback() {
      this._abortController = new AbortController();
      const signal = this._abortController.signal;

      // The web component can connect to the DOM before its dialog child.
      const waitForDialog = new Promise((resolve, reject) => {
        const dialog = this.querySelector(
          'dialog, div#drupal-off-canvas-wrapper',
        );
        const observer = new MutationObserver(() => {
          const element = this.querySelector(
            'dialog, div#drupal-off-canvas-wrapper',
          );
          if (element) {
            observer.disconnect();
            resolve(element);
          }
        });

        if (dialog) {
          resolve(dialog);
        } else {
          observer.observe(this, {
            childList: true,
            subtree: true,
          });
        }
        signal.addEventListener('abort', () => {
          observer.disconnect();
          reject(signal.reason);
        });
      });

      waitForDialog.then(
        (dialog) => {
          // Guard against a disconnection while waiting for dialog.
          if (!this.isConnected) return;
          // Remove self from DOM on close unless data-persist or skipClose.
          let skipClose = false;
          dialog.addEventListener(
            'close',
            (e) => {
              if (e.target !== dialog) return;
              if (skipClose) {
                skipClose = false;
                return;
              }
              if (!this.hasAttribute('data-persist')) this.remove();
              if (
                document.body.classList.contains('js-off-canvas-dialog-open')
              ) {
                document.body.classList.remove('js-off-canvas-dialog-open');
              }
            },
            { signal },
          );

          switch (dialog.tagName.toLowerCase()) {
            case 'dialog':
              // Backdrop polyfill for closedby="any" on Safari.
              if (
                !supportsClosedBy &&
                dialog.getAttribute('closedby') === 'any'
              ) {
                this._backdropHandler = (e) => {
                  if (e.target === dialog) dialog.close();
                };
                dialog.addEventListener('click', this._backdropHandler, {
                  signal,
                });
              }
              if (dialog.hasAttribute('open')) {
                skipClose = true;
                dialog.close();
                if (
                  dialog.hasAttribute('data-modal') ||
                  this._backdropHandler
                ) {
                  // Our current polyfill needs the entire screen to be a click target.
                  dialog.showModal();
                } else {
                  dialog.show();
                }
              }

              break;
            case 'div': {
              // Initialize the off-canvas dialog.
              const closeButton = dialog.querySelector(
                '.dialog__titlebar-close',
              );
              if (closeButton) {
                closeButton.addEventListener(
                  'click',
                  (event) => {
                    event.preventDefault();
                    const closeEvent = new Event('close', { bubbles: false });
                    dialog.dispatchEvent(closeEvent);
                  },
                  { signal },
                );
              }
              this._resizeObserver = new ResizeObserver(() => {
                this.adjustForDisplacement(dialog);
              });
              this._resizeObserver.observe(dialog);
              signal.addEventListener('abort', () => {
                this._resizeObserver?.disconnect();
              });
              // Set the expected body class.
              document.body.classList.add('js-off-canvas-dialog-open');
              break;
            }
            default:
              return;
          }
        },
        (reason) => {
          console.warn(
            `DrupalDialog.waitForDialog aborted before dialog was found. Reason: ${reason}`,
          );
        },
      );
    }

    disconnectedCallback() {
      // Clean up and close silently if still open.
      this._abortController?.abort('DrupalDialog disconnected');
      if (this._resizeObserver) this._resizeObserver = null;
      const dialog = this.querySelector(
        'dialog, div#drupal-off-canvas-wrapper',
      );
      if (!dialog) return;
      // Our htmx integration detaches behaviors, but since this is an SDC
      // we also need to call it as this may be used elsewhere.
      Drupal.detachBehaviors(this, drupalSettings, 'unload');
      // Was the web component removed without calling dialog.close()?
      if (dialog instanceof HTMLDialogElement && dialog.open) dialog.close();
      if (dialog instanceof HTMLDivElement) {
        this.adjustForDisplacement(dialog, true);
      }
    }
  }

  customElements.define('drupal-dialog', DrupalDialog);
})(Drupal, drupalSettings, Drupal.displace);
