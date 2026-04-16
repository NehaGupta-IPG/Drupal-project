/**
 * @file
 * Attaches behaviors for adding tip functionality.
 */
// cspell:ignore UIDOM Wolfson

((
  Drupal,
  displace,
  { computePosition, flip, shift, offset, autoUpdate, arrow },
) => {
  Drupal.tip = {
    defaultConfig: {
      arrowPadding: 5,
      placement: 'top-start',
      offset: 24,
      shiftPadding: 5,
    },
  };
  Drupal.behaviors.tip = {
    attach(context) {
      const { defaultConfig } = Drupal.tip;

      once(
        'drupal-tip-trigger',
        '[data-drupal-tip-toggle-button]',
        context,
      ).forEach((tipTrigger) => {
        const tipId = tipTrigger.getAttribute('popovertarget');

        // Check if tip already exists in DOM.
        let tip = document.getElementById(tipId);

        // If not found, create it dynamically.
        if (!tip) {
          tip = Drupal.theme.tip(
            tipId,
            tipTrigger.dataset.drupalTipToggleButton,
          );
          tipTrigger.after(tip);
        }

        const config = { ...defaultConfig };

        // [data-drupal-tip-placement] from https://floating-ui.com/docs/computePosition#placement-1
        if (tipTrigger.dataset.drupalTipPlacement) {
          config.placement = tipTrigger.dataset.drupalTipPlacement;
        }

        // [data-drupal-tip-on-hover]
        if (tipTrigger?.dataset?.drupalTipOnHover) {
          tipTrigger.addEventListener('mouseenter', () => {
            tip.showPopover();
          });
          tipTrigger.addEventListener('mouseleave', () => {
            tip.hidePopover();
          });
        }

        // Create the arrow that points to the tip's disclosure button.
        // Check if arrow already exists (from PHP template).
        let tipArrow = tip.querySelector('.tip__arrow');
        if (!tipArrow) {
          tipArrow = Drupal.theme.tipArrow(tip);
        }

        const updatePosition = () => {
          // This is kind of Virtual Element for shift middleware.
          const adjustedOffsets = Object.entries(displace.offsets).reduce(
            (acc, [key, value]) => {
              acc[key] = value + config.shiftPadding;
              return acc;
            },
            {},
          );

          computePosition(tipTrigger, tip, {
            strategy: 'fixed',
            placement: config.placement,
            middleware: [
              offset(config.offset),
              // Account for Drupal's top offset so the toggletip is not hidden
              // under the toolbar.
              flip({
                padding: {
                  top: displace.offsets.top,
                },
              }),
              shift({ padding: { ...adjustedOffsets } }),
              arrow({ element: tipArrow, padding: config.arrowPadding }),
            ],
          }).then(({ x, y, placement, middlewareData }) => {
            Object.assign(tip.style, {
              left: `${x}px`,
              top: `${y}px`,
            });

            // Use middleware to dynamically position the arrow.
            const { x: arrowX, y: arrowY } = middlewareData.arrow;

            // Placement will the opposite of the tip's primary axis.
            const staticSide = {
              top: 'bottom',
              right: 'left',
              bottom: 'top',
              left: 'right',
            }[placement.split('-')[0]];

            Object.assign(tipArrow.style, {
              left: arrowX != null ? `${arrowX}px` : '',
              top: arrowY != null ? `${arrowY}px` : '',
              [staticSide]: '-4px',
            });
          });
        };

        // Position the toggletip.
        autoUpdate(tipTrigger, tip, updatePosition, {
          elementResize: false,
        });

        // We better will update position on popover toggle
        // instead of resize tip resize observer.
        tip.addEventListener('beforetoggle', (e) => {
          if (e?.newState === 'open') {
            tip.style.visibility = 'hidden';
          }
        });
        tip.addEventListener('toggle', (e) => {
          if (e?.newState === 'open') {
            updatePosition();
            tip.style.visibility = 'visible';
          }
        });
      });
    },
  };

  /**
   * Theme function for a tip.
   *
   * @param {string} tipId
   *   The tipId.
   * @param {string} content
   *   The tip text.
   *
   * @return {HTMLElement}
   *   A DOM Node.
   */
  Drupal.theme.tip = (tipId, content) => {
    const tip = document.createElement('div');
    tip.classList.add('tip');
    tip.setAttribute('tabindex', '0');
    tip.setAttribute('role', 'status');
    tip.setAttribute('data-drupal-tip', true);
    tip.id = tipId;
    tip.setAttribute('popover', '');
    tip.innerHTML = content;
    return tip;
  };

  /**
   * Theme function for a tipArrow.
   *
   * @param {HTMLElement} tip
   *   The tip.
   *
   * @return {HTMLElement}
   *   A DOM Node.
   */
  Drupal.theme.tipArrow = (tip) => {
    const tipArrow = document.createElement('div');
    tipArrow.classList.add('tip__arrow');
    tip.append(tipArrow);
    return tipArrow;
  };
})(Drupal, Drupal.displace, window.FloatingUIDOM);
