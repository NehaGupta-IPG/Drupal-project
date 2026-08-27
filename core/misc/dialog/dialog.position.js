/**
 * @file
 * Positioning extensions for dialogs.
 */

/**
 * Triggers when content inside a dialog changes.
 *
 * @event dialogContentResize
 */

(function ($, Drupal, drupalSettings, debounce, displace) {
  // autoResize option will turn off resizable and draggable.
  drupalSettings.dialog = $.extend(
    { autoResize: true, maxHeight: '95%' },
    drupalSettings.dialog,
  );

  /**
   * Position the dialog's center at the center of displace.offsets boundaries.
   *
   * @function Drupal.dialog~resetPosition
   *
   * @param {object} options
   *   Options object.
   *
   * @return {object}
   *   Altered options object.
   */
  function resetPosition(options) {
    const offsets = displace.offsets;
    const left = offsets.left - offsets.right;
    const top = offsets.top - offsets.bottom;

    const leftString = `${
      (left > 0 ? '+' : '-') + Math.abs(Math.round(left / 2))
    }px`;
    const topString = `${
      (top > 0 ? '+' : '-') + Math.abs(Math.round(top / 2))
    }px`;
    options.position = {
      my: `center${left !== 0 ? leftString : ''} center${
        top !== 0 ? topString : ''
      }`,
      of: window,
    };
    return options;
  }

  /**
   * Resets the current options for positioning.
   *
   * This is used as a window resize and scroll callback to reposition the
   * jQuery UI dialog. Although not a built-in jQuery UI option, this can
   * be disabled by setting autoResize: false in the options array when creating
   * a new {@link Drupal.dialog}.
   *
   * @function Drupal.dialog~resetSize
   *
   * @param {jQuery.Event} event
   *   The event triggered.
   *
   * @fires event:dialogContentResize
   */
  function resetSize(event) {
    if (!event?.data?.settings || !event?.data?.$element) {
      return;
    }
    const $element = event.data.$element;
    const settings = event.data.settings;
    if (!$element.length || !document.contains($element[0])) {
      return;
    }
    const positionOptions = [
      'width',
      'height',
      'minWidth',
      'minHeight',
      'maxHeight',
      'maxWidth',
      'position',
    ];
    let adjustedOptions = {};
    let windowHeight = $(window).height();
    let option;
    let optionValue;
    let adjustedValue;
    for (let n = 0; n < positionOptions.length; n++) {
      option = positionOptions[n];
      optionValue = settings[option];
      if (optionValue) {
        // jQuery UI does not support percentages on heights, convert to pixels.
        if (
          typeof optionValue === 'string' &&
          optionValue.endsWith('%') &&
          /height/i.test(option)
        ) {
          // Take offsets in account.
          windowHeight -= displace.offsets.top + displace.offsets.bottom;
          adjustedValue = parseInt(
            0.01 * parseInt(optionValue, 10) * windowHeight,
            10,
          );
          // Don't force the dialog to be bigger vertically than needed.
          if (
            option === 'height' &&
            Math.round($element.parent().outerHeight()) < adjustedValue
          ) {
            adjustedValue = 'auto';
          }
          adjustedOptions[option] = adjustedValue;
        }
      }
    }
    // Offset the dialog center to be at the center of Drupal.displace.offsets.
    if (!settings.modal) {
      adjustedOptions = resetPosition(adjustedOptions);
    }
    $element.dialog('option', adjustedOptions);

    $element
      ?.get(0)
      ?.dispatchEvent(
        new CustomEvent('dialogContentResize', { bubbles: true }),
      );
  }

  window.addEventListener('dialog:aftercreate', (e) => {
    const autoResize = debounce(resetSize, 20);
    const $element = $(e.target);
    const { settings } = e;
    const eventData = { settings, $element };

    if (settings.autoResize === true || settings.autoResize === 'true') {
      const uiDialog = $element
        .dialog('option', { resizable: false, draggable: false })
        .dialog('widget');
      uiDialog[0].style.position = 'fixed';
      $(window)
        .on('resize.dialogResize scroll.dialogResize', eventData, autoResize)
        .trigger('resize.dialogResize');
      $(document).on(
        'drupalViewportOffsetChange.dialogResize',
        eventData,
        autoResize,
      );
      // Defer reposition so modal centers correctly with AJAX content.
      const deferredReposition = () => {
        if (!$element.length || !document.contains($element[0])) {
          return;
        }
        $(window).trigger('resize.dialogResize');
      };
      if (typeof requestAnimationFrame !== 'undefined') {
        requestAnimationFrame(() => {
          requestAnimationFrame(deferredReposition);
        });
      }
      setTimeout(deferredReposition, 150);

      // Reposition when dialog content changes (e.g. AJAX insert).
      const contentNode = $element[0];
      const resizeOnContentChange = () => {
        deferredReposition();
      };
      const positionObserver = new MutationObserver(
        debounce(resizeOnContentChange, 50),
      );
      positionObserver.observe(contentNode, {
        childList: true,
        subtree: true,
      });
      contentNode._dialogPositionObserver = positionObserver;
    }
  });

  window.addEventListener('dialog:beforeclose', (e) => {
    const dialogElement = e.target;
    if (dialogElement._dialogPositionObserver) {
      dialogElement._dialogPositionObserver.disconnect();
      delete dialogElement._dialogPositionObserver;
    }
    $(window).off('.dialogResize');
    $(document).off('.dialogResize');
  });
})(jQuery, Drupal, drupalSettings, Drupal.debounce, Drupal.displace);
