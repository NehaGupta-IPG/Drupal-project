/**
 * @file
 * Extends dialog sizing and positioning.
 *
 * Converts sizes and sets fixed positioning before opening. Updates percentage
 * heights and non-modal positions on viewport or displacement changes.
 */

/**
 * Signals that dialog content dimensions may have changed.
 *
 * Bubbles from the dialog content element after opening or automatic size or
 * position updates when autoResize is enabled.
 *
 * @event dialogContentResize
 */

(function ($, Drupal, drupalSettings, debounce, displace) {
  // autoResize option will turn off resizable and draggable.
  drupalSettings.dialog = $.extend(
    { autoResize: true, maxHeight: '95%' },
    drupalSettings.dialog,
  );

  $.widget('ui.dialog', $.ui.dialog, {
    _create() {
      const sizes = getSizeOptions(this.options);
      // Preserve percentage heights for natural content sizing in _size().
      delete sizes.height;
      Object.assign(this.options, sizes);
      this._super();
    },
    _setOptions(options) {
      this._super({ ...options, ...getSizeOptions(options, this.uiDialog) });
    },
    _size() {
      let sizes = getSizeOptions(this.options);
      if (
        typeof this.options.height === 'string' &&
        this.options.height.trim().endsWith('%') &&
        Number.isFinite(sizes.height)
      ) {
        const settings = { ...this.options };
        // Measure natural height before applying the percentage height.
        Object.assign(this.options, sizes, { height: 'auto' });
        this._super();
        sizes = getSizeOptions(settings, this.uiDialog);
      }
      Object.assign(this.options, sizes);
      this._super();
    },
    open() {
      if (this.options.autoResize && !this.options.modal && !this._isOpen) {
        resetPosition(this.options);
      }
      // Set fixed positioning before jQuery UI calculates the coordinates.
      // Explicitly reposition already open dialogs because open() skips them.
      const style = this.uiDialog[0].style;
      if (this.options.autoResize && style.position !== 'fixed') {
        style.position = 'fixed';
        if (this._isOpen) {
          this._position();
        }
      }
      this._super();
    },
  });

  /**
   * Converts dialog size options to pixel values or automatic height.
   *
   * Processes width, height, minWidth, minHeight, maxWidth, and maxHeight.
   * Pixel sizes accept numbers, or strings with an optional "px" suffix.
   * The height, minHeight, and maxHeight options also accept percentages of
   * the viewport height minus Drupal displacement offsets. Negative sizes are
   * clamped to zero; percentage heights are truncated to whole pixels.
   *
   * @param {object} settings
   *   The dialog options to convert. This object is not modified.
   * @param {jQuery} [$widget]
   *   The measured dialog wrapper. If a percentage height exceeds its measured
   *   height, returns "auto" for that option to avoid enlarging short content.
   *
   * @return {object}
   *   Converted size options; unconverted values such as "auto" are omitted.
   */
  function getSizeOptions(settings, $widget) {
    const sizes = {};
    for (const option of [
      'width',
      'height',
      'minWidth',
      'minHeight',
      'maxWidth',
      'maxHeight',
    ]) {
      const value = settings[option];
      let size = value;
      let percentage;

      if (typeof value === 'string') {
        [, size, percentage] = value.trim().match(/^(.*?)(?:px|(%))?$/i) ?? [];
        if (size === '') {
          continue;
        }
      } else if (typeof size !== 'number') {
        continue;
      }

      size = Math.max(0, Number(size));
      if (!Number.isFinite(size)) {
        continue;
      }

      if (percentage) {
        if (!/height/i.test(option)) {
          continue;
        }
        const availableHeight =
          $(window).height() - displace.offsets.top - displace.offsets.bottom;
        size = Math.max(0, Math.trunc((size / 100) * availableHeight));

        if (
          option === 'height' &&
          $widget &&
          Math.round($widget.outerHeight()) < size
        ) {
          size = 'auto';
        }
      }

      sizes[option] = size;
    }
    return sizes;
  }

  /**
   * Position the dialog's center at the center of displace.offsets boundaries.
   *
   * Other position options, including collision handling, are preserved.
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
      ...$.ui.dialog.prototype.options.position,
      ...options.position,
      at: 'center',
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
   * Only percentage heights are recalculated; pixel sizes are not reapplied.
   *
   * @function Drupal.dialog~resetSize
   *
   * @param {jQuery.Event} event
   *   The event triggered.
   *
   * @fires event:dialogContentResize
   */
  function resetSize(event) {
    // Ensure the UI dialog instance exists/is valid.
    if (!event.data?.$element?.data('ui-dialog')) {
      return;
    }

    const percentageSettings = Object.fromEntries(
      Object.entries(event.data.settings).filter(
        ([, value]) =>
          typeof value === 'string' && value.trim().endsWith('%'),
      ),
    );
    let adjustedOptions = getSizeOptions(
      percentageSettings,
      event.data.$element.parent(),
    );
    // Offset the dialog center to be at the center of Drupal.displace.offsets.
    if (!event.data.settings.modal) {
      adjustedOptions = resetPosition(adjustedOptions);
    }
    event.data.$element.dialog('option', adjustedOptions);

    dispatchContentResize(event.data.$element);
  }

  /**
   * Notifies listeners that the dialog content size may have changed.
   *
   * @param {jQuery} $element
   *   The dialog element.
   */
  function dispatchContentResize($element) {
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

    if (settings.autoResize) {
      $element.dialog('option', { resizable: false, draggable: false });
      // Keep a reference so that a scheduled call can be cancelled on close.
      $element.data('drupalAutoResize', autoResize);
      // prettier-ignore
      $(window)
        .on('resize.dialogResize scroll.dialogResize', eventData, autoResize);
      $(document).on(
        'drupalViewportOffsetChange.dialogResize',
        eventData,
        autoResize,
      );
      dispatchContentResize($element);
    }
  });

  window.addEventListener('dialog:beforeclose', (e) => {
    // Unbinding the handlers below does not stop a resize that is already
    // scheduled. Closing a dialog removes its element from the DOM, which
    // destroys the jQuery UI instance, so a call that runs after this point
    // would act on a destroyed dialog and throw.
    $(e.target).data('drupalAutoResize')?.cancel();
    $(window).off('.dialogResize');
    $(document).off('.dialogResize');
  });
})(jQuery, Drupal, drupalSettings, Drupal.debounce, Drupal.displace);
