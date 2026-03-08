/* cspell:ignore autoupdate, uidom */
/**
 * @file
 * Positioning extensions for dialogs.
 */

/**
 * Triggers when content inside a dialog changes.
 *
 * @event dialogContentResize
 */

(function (
  $,
  drupalSettings,
  displace,
  { autoUpdate, computePosition, offset, size },
) {
  // autoResize option will turn off resizable and draggable.
  drupalSettings.dialog = $.extend(
    { autoResize: true, maxHeight: '95%' },
    drupalSettings.dialog,
  );

  // Feature of floating UI.
  // https://floating-ui.com/docs/virtual-elements

  const getVirtualEl = ({ settings }) => {
    const availableSpace = {
      x: 0,
      y: 0,
      top: 0,
      left: 0,
      bottom: 0,
      right: 0,
      width: window.innerWidth,
      height: window.innerHeight,
    };

    if (!settings.modal) {
      availableSpace.top = displace.offsets.top;
      availableSpace.left = displace.offsets.left;
      availableSpace.bottom = displace.offsets.bottom;
      availableSpace.right = displace.offsets.right;
      availableSpace.width =
        window.innerWidth - displace.offsets.left - displace.offsets.right;
      availableSpace.height =
        window.innerHeight - displace.offsets.top - displace.offsets.bottom;
    }

    return {
      getBoundingClientRect() {
        return availableSpace;
      },
    };
  };

  const resetPositionFloatingUI = (e) => {
    const widget = e.target.closest('[role="dialog"]');
    const virtualEl = getVirtualEl(e);

    const updatePosition = () => {
      computePosition(getVirtualEl(e), widget, {
        strategy: 'fixed',
        middleware: [
          offset(({ rects }) => {
            return {
              mainAxis: -rects.reference.height / 2 - rects.floating.height / 2,
            };
          }),
          size({
            apply({ availableWidth, availableHeight, elements }) {
              Object.assign(elements.floating.style, {
                maxWidth: `${availableWidth}px`,
                maxHeight: `${availableHeight}px`,
              });
            },
          }),
        ],
      }).then(({ x, y }) => {
        Object.assign(widget.style, {
          left: `${x}px`,
          top: `${y}px`,
        });
      });

      // Same custom event.
      // https://www.drupal.org/project/drupal/issues/3445033
      e.target?.dispatchEvent(
        new CustomEvent('dialogContentResize', { bubbles: true }),
      );
    };

    // Feature of floating UI.
    // https://floating-ui.com/docs/autoUpdate
    const cleanup = autoUpdate(virtualEl, widget, updatePosition);

    return { cleanup, updatePosition };
  };

  window.addEventListener('dialog:aftercreate', (e) => {
    const $element = $(e.target);
    const { settings } = e;
    const eventData = { settings, $element };

    if (settings.autoResize === true || settings.autoResize === 'true') {
      const uiDialog = $element
        .dialog('option', { resizable: false, draggable: false })
        .dialog('widget');
      uiDialog[0].style.position = 'fixed';

      const { cleanup, updatePosition } = resetPositionFloatingUI(e);
      e.target.addEventListener('disable-resize-autoupdate', () => {
        cleanup();
      });

      $(document).on(
        'drupalViewportOffsetChange.dialogResize',
        eventData,
        updatePosition,
      );
    }
  });

  window.addEventListener('dialog:beforeclose', (e) => {
    e.target.dispatchEvent(new CustomEvent('disable-resize-autoupdate'));
    $(document).off('.dialogResize');
  });
})(jQuery, drupalSettings, Drupal.displace, FloatingUIDOM);
