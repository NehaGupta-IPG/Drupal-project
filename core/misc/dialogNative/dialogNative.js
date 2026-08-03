/**
 * @file
 * Dialog API inspired by HTML5 dialog element.
 *
 * @see http://www.whatwg.org/specs/web-apps/current-work/multipage/commands.html#the-dialog-element
 */

(() => {
  /**
   * Default dialog options.
   *
   * @type {object}
   *
   * @prop {boolean} [autoOpen=true]
   * @prop {string} [dialogClass='']
   * @prop {string} [buttonClass='button']
   * @prop {string} [buttonPrimaryClass='button--primary']
   * @prop {function} close
   */
  drupalSettings.dialog = {
    autoOpen: true,
    dialogClass: '',
    // Drupal-specific extensions: see dialog.jquery-ui.js.
    buttonClass: 'button',
    buttonPrimaryClass: 'button--primary',
    // When using this API directly (when generating dialogs on the client
    // side), you may want to override this method and do
    // `(event.target).remove();` as well, to remove the dialog on
    // closing.
    close(event) {
      Drupal.detachBehaviors(event.target, null, 'unload');
    },
  };

  /**
   * Creates a native html5 dialog.
   *
   * @param {HTMLElement} element
   *   The HTML element to be inserted into the dialog container.
   * @param {object} settings
   *   The settings for the dialog.
   * @param {string} [settings.dialogClass]
   *   The class(es) to be added to the dialog container.
   * @param {boolean} [settings.closeOnEscape=true]
   *   Determines whether the dialog should close on pressing the Escape key.
   * @param {string} [settings.title]
   *   The title of the dialog.
   * @param {Array} [settings.buttons]
   *   An array of buttons to be added to the dialog.
   * @param {string} settings.buttons[].text
   *   The text content of the button.
   * @param {string} settings.buttons[].class
   *   The class(es) to be added to the button element.
   * @param {Function} [settings.buttons[].click]
   *   The click event handler for the button.
   * @return {HTMLElement}
   *   The created dialog container.
   */
  function createDialog(element, settings) {
    const dialogContainer = document.createElement('dialog');
    dialogContainer.id = 'drupal-modal';
    // Add dialog classes.
    if (settings.dialogClass) {
      dialogContainer.classList.add(...settings.dialogClass.split(' '));
    }

    if (settings.closeOnEscape === false) {
      dialogContainer.addEventListener('cancel', (event) => {
        event.preventDefault();
      });
    }

    // Add dialog title.
    if (settings.title) {
      dialogContainer.insertAdjacentHTML(
        'beforeend',
        `<h4>${settings.title}</h4>`,
      );
    }

    // Add dialog content.
    if (typeof element === 'string') {
      dialogContainer.insertAdjacentHTML('beforeend', element);
    } else {
      // remove old class & id; temporary workaround.
      if (element.id === 'drupal-modal') {
        element.removeAttribute('id');
      }
      element.classList.remove('ui-front');

      dialogContainer.appendChild(element);
    }

    // Add dialog buttons.
    if (settings.buttons) {
      settings.buttons.forEach((button) => {
        const buttonElement = document.createElement('button');
        buttonElement.textContent = button.text;
        buttonElement.classList.add(...button.class.split(' '));
        if (button.click) {
          buttonElement.addEventListener('click', button.click);
        }
        dialogContainer.appendChild(buttonElement);
      });
    }

    // Add dialog event listener and attach to body.
    dialogContainer.addEventListener('close', settings.close);
    document.body.appendChild(dialogContainer);

    return dialogContainer;
  }

  /**
   * @typedef {object} Drupal.dialog~dialogDefinition
   *
   * @param {HTMLElement|string} element
   *   The HTML element / string to be inserted into the dialog container.
   * @param {object} settings
   *   The dialog options.
   * @return {HTMLElement} dialog
   *   The dialog element.
   */
  Drupal.dialogNative = function dialogNative(element, settings) {
    return createDialog(element, settings);
  };
})();
