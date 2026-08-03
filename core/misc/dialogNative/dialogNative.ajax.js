/**
 * @file
 * Extends the Drupal AJAX functionality to integrate the dialog API.
 */

(function ($, Drupal, { focusable }) {
  /**
   * Initialize dialogs for Ajax purposes.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behaviors for dialog ajax functionality.
   */
  Drupal.behaviors.dialogNative = {
    attach(context, settings) {
      console.log(settings);
      const drupalModal = context.querySelector('#drupal-modal');

      // Remove existing jQuery-ui modals.
      // Non-modal dialogs are responsible for creating their own
      // elements, since there can be multiple non-modal dialogs at a time.
      if (drupalModal) {
        drupalModal.remove();
      }
    },

    /**
     * Scan a dialog for any primary buttons and move them to the button area.
     *
     * @param {jQuery} $dialog
     *   A jQuery object containing the element that is the dialog target.
     *
     * @return {Array}
     *   An array of buttons that need to be added to the button area.
     */
    // prepareDialogButtons($dialog) {
    //   const buttons = [];
    //   const $buttons = $dialog.find(
    //     '.form-actions input[type=submit], .form-actions a.button',
    //   );
    //   $buttons.each(function () {
    //     const $originalButton = $(this).css({ display: 'none' });
    //     buttons.push({
    //       text: $originalButton.html() || $originalButton.attr('value'),
    //       class: $originalButton.attr('class'),
    //       click(e) {
    //         // If the original button is an anchor tag, triggering the "click"
    //         // event will not simulate a click. Use the click method instead.
    //         if ($originalButton.is('a')) {
    //           $originalButton[0].click();
    //         } else {
    //           $originalButton
    //             .trigger('mousedown')
    //             .trigger('mouseup')
    //             .trigger('click');
    //           e.preventDefault();
    //         }
    //       },
    //     });
    //   });
    //   return buttons;
    // },
  };

  /**
   * Command to open a dialog.
   *
   * @param {Drupal.Ajax} ajax
   *   The Drupal Ajax object.
   * @param {object} response
   *   Object holding the server response.
   * @param {number} [status]
   *   The HTTP status code.
   *
   * @return {boolean|undefined}
   *   Returns false if there was no selector property in the response object.
   */
  Drupal.AjaxCommands.prototype.openDialog = function (ajax, response, status) {
    if (!response.selector) {
      return false;
    }

    // Open the dialog itself.
    response.dialogOptions = response.dialogOptions || {};
    const dialog = Drupal.dialogNative(response.data, response.dialogOptions);
    if (response.dialogOptions.modal) {
      dialog.showModal();
    } else {
      dialog.show();
    }

    // Add the standard Drupal class for buttons for style consistency.
    //$dialog.parent().find('.ui-dialog-buttonset').addClass('form-actions');
  };

  /**
   * Command to close a dialog.
   *
   * If no selector is given, it defaults to trying to close the modal.
   *
   * @param {Drupal.Ajax} [ajax]
   *   The ajax object.
   * @param {object} response
   *   Object holding the server response.
   * @param {string} response.selector
   *   The selector of the dialog.
   * @param {boolean} response.persist
   *   Whether to persist the dialog element or not.
   * @param {number} [status]
   *   The HTTP status code.
   */
  Drupal.AjaxCommands.prototype.closeDialog = function (
    ajax,
    response,
    status,
  ) {
    const $dialog = $(response.selector);
    if ($dialog.length) {
      Drupal.dialogNative($dialog.get(0)).close();
      if (!response.persist) {
        $dialog.remove();
      }
    }

    // Unbind dialogButtonsChange.
    $dialog.off('dialogButtonsChange');
  };

  /**
   * Command to set a dialog property.
   *
   * JQuery UI specific way of setting dialog options.
   *
   * @param {Drupal.Ajax} [ajax]
   *   The Drupal Ajax object.
   * @param {object} response
   *   Object holding the server response.
   * @param {string} response.selector
   *   Selector for the dialog element.
   * @param {string} response.optionsName
   *   Name of a key to set.
   * @param {string} response.optionValue
   *   Value to set.
   * @param {number} [status]
   *   The HTTP status code.
   */
  Drupal.AjaxCommands.prototype.setDialogOption = function (
    ajax,
    response,
    status,
  ) {
    const $dialog = $(response.selector);
    if ($dialog.length) {
      $dialog.dialogNative('option', response.optionName, response.optionValue);
    }
  };

  /**
   * Binds a listener on dialog creation to handle the cancel link.
   *
   * @param {event} e
   *   The event triggered. Contains dialog, element & settings
   */
  window.addEventListener('dialogNative:aftercreate', (e) => {
    console.log('dialogNative:aftercreate');
    console.log(e.detail.dialog.element);
    // todo
    // e.detail.dialog.element.querySelector('.dialog-cancel').addEventListener('click', (event)=>{
    //   // event.preventDefault();
    //   // event.stopPropagation();
    //   // console.log('cancel');
    // });
    // e.detail.dialog.element.addEventListener('cancel', (event) => {
    //   // event.preventDefault();
    //   // event.stopPropagation();
    //   // console.log('cancel');
    //
    // });
  });
  // $(window).on('dialog:aftercreate', (e, dialog, $element, settings) => {
  //   $element.on('click.dialog', '.dialog-cancel', (e) => {
  //     dialog.close('cancel');
  //     e.preventDefault();
  //     e.stopPropagation();
  //   });
  // });

  /**
   * Removes all 'dialog' listeners.
   *
   * @param {jQuery.Event} e
   *   The event triggered.
   * @param {Drupal.dialog~dialogDefinition} dialog
   *   The dialog instance.
   * @param {jQuery} $element
   *   jQuery collection of the dialog element.
   */
  window.addEventListener('dialogNative:beforeclose', (e, dialog, $element) => {
    console.log('dialogNative:beforeclose');
    // todo? remove dialog eventhandlers
  })

})(jQuery, Drupal, window.tabbable);
