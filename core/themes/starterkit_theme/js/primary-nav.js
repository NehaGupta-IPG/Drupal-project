/**
 * @file
 * Provides all main menu interactions.
 */

((Drupal, once) => {
  let primaryNavigationRegion;
  let mobileNavigationButton;
  let secondLevelNavMenus;
  let secondLevelToggleButtonSelector;

  /**
   * Determines if the mobile navigation is open by checking if its
   * disclosure button is visible.
   *
   * @returns {boolean}
   */
  function isDesktopNav() {
    return mobileNavigationButton.clientHeight === 0;
  }

  /**
   * Checks if primaryNavigationRegion contains "is-expanded" class.
   *
   * @return {boolean}
   *   True if navWrapper contains "is-expanded" class, false if not.
   */
  function isMobileNavExpanded() {
    return (
      !isDesktopNav() &&
      primaryNavigationRegion.classList.contains('is-expanded')
    );
  }

  /**
   * Shows and hides the specified menu item's second level submenu.
   *
   * @param {Element} topLevelMenuItem
   *   The <li> element that is the container for the menu and submenus.
   * @param {boolean} [toState]
   *   Optional state where we want the submenu to end up.
   */
  function toggleSubNav(topLevelMenuItem, toState) {
    const button = topLevelMenuItem.querySelector(
      secondLevelToggleButtonSelector,
    );
    const secondLevelMenuSelector =
      '[data-drupal-selector="primary-nav-menu--level-2"]';
    const state =
      toState !== undefined
        ? toState
        : button.getAttribute('aria-expanded') !== 'true';

    if (state) {
      // If desktop nav, ensure all menus close before expanding new one.
      if (isDesktopNav()) {
        secondLevelNavMenus.forEach((el) => {
          el.querySelector(secondLevelToggleButtonSelector).setAttribute(
            'aria-expanded',
            'false',
          );
          el.querySelector(secondLevelMenuSelector).classList.remove(
            'is-active-menu-parent',
          );
        });
      }
      button.setAttribute('aria-expanded', 'true');
      topLevelMenuItem
        .querySelector(secondLevelMenuSelector)
        .classList.add('is-active-menu-parent');
    } else {
      button.setAttribute('aria-expanded', 'false');
      topLevelMenuItem.classList.remove('is-touch-event');
      topLevelMenuItem
        .querySelector(secondLevelMenuSelector)
        .classList.remove('is-active-menu-parent');
    }
  }

  /**
   * Sets a timeout and closes current desktop navigation submenu if it
   * does not contain the focused element.
   *
   * @param {Event} e
   *   The event object.
   */
  function handleSecondLevelBlur(e) {
    if (!isDesktopNav()) return;

    setTimeout(() => {
      const menuParentItem = e.target.closest(
        '[data-drupal-selector="primary-nav-menu-item-has-children"]',
      );
      if (!menuParentItem.contains(document.activeElement)) {
        toggleSubNav(menuParentItem, false);
      }
    }, 200);
  }

  /**
   * Sets up event listeners to manage display of second-level menus. Also
   * contains logic of when event listeners will fire to ensure that touch
   * devices do not simultaneously fire mouseover and click events.
   *
   * @param {NodeList} secondLevelNavMenus
   *   NodeList containing all top-level <li> elements that have submenus.
   */
  function initSecondLevelNav(secondLevelNavMenus) {
    secondLevelNavMenus.forEach((el) => {
      const button = el.querySelector(secondLevelToggleButtonSelector);

      // If touch event, prevent mouseover event from triggering the submenu.
      el.addEventListener(
        'touchstart',
        () => {
          el.classList.add('is-touch-event');
        },
        { passive: true },
      );

      el.addEventListener('mouseover', () => {
        if (isDesktopNav() && !el.classList.contains('is-touch-event')) {
          el.classList.add('is-active-mouseover-event');
          toggleSubNav(el, true);

          // Timeout is added to ensure that users of assistive devices (such as
          // mouse grid tools) do not simultaneously trigger both the mouseover
          // and click events. When these events are triggered together, the
          // submenu will appear to not open.
          setTimeout(() => {
            el.classList.remove('is-active-mouseover-event');
          }, 500);
        }
      });

      // Only fire click event if the mouseover event has not just fired.
      button.addEventListener('click', () => {
        if (!el.classList.contains('is-active-mouseover-event')) {
          toggleSubNav(el);
        }
      });

      // Only close menu on mouseout if it does not contain focused element.
      el.addEventListener('mouseout', () => {
        if (
          isDesktopNav() &&
          !document.activeElement.matches(
            '[aria-expanded="true"], .is-active-menu-parent *',
          )
        ) {
          toggleSubNav(el, false);
        }
      });

      el.addEventListener('blur', handleSecondLevelBlur, true);
    });
  }

  /**
   * Close all second level sub navigation menus.
   */
  function closeAllSubNav() {
    secondLevelNavMenus.forEach((el) => {
      // Return focus to the toggle button if the submenu contains focus.
      if (el.contains(document.activeElement)) {
        el.querySelector(secondLevelToggleButtonSelector).focus();
      }
      toggleSubNav(el, false);
    });
  }

  /**
   * Expand/collapse mobile navigation.
   *
   * @param {boolean} toState - if navigation will be expanded or collapsed.
   */
  function toggleMobileNav(toState) {
    if (primaryNavigationRegion.contains(document.activeElement)) {
      mobileNavigationButton.focus();
    }

    document.body.classList.toggle('is-active-mobile-menu', toState);
    mobileNavigationButton.setAttribute('aria-expanded', toState);
    primaryNavigationRegion.classList.toggle('is-expanded', toState);
  }

  /**
   * Handle mobile navigation click event.
   *
   * @param {Event} e
   *   The event object.
   */
  function handleMobileNavigationButtonClick(e) {
    const isExpanded = e.currentTarget.getAttribute('aria-expanded') === 'true';
    toggleMobileNav(!isExpanded);
  }

  /**
   * Focus trap for mobile menu.
   *
   * @param {Event} e - The event object.
   */
  function focusTrap(e) {
    if (e.key === 'Tab' && isMobileNavExpanded()) {
      const tabbableNavElements = tabbable.tabbable(primaryNavigationRegion);
      tabbableNavElements.unshift(mobileNavigationButton);
      const firstTabbableEl = tabbableNavElements[0];
      const lastTabbableEl =
        tabbableNavElements[tabbableNavElements.length - 1];

      if (e.shiftKey) {
        if (document.activeElement === firstTabbableEl) {
          lastTabbableEl.focus();
          e.preventDefault();
        }
      } else if (document.activeElement === lastTabbableEl) {
        firstTabbableEl.focus();
        e.preventDefault();
      }
    }
  }

  /**
   * Initialize the primary navigation interactions.
   *
   * @param {Element} - Primary navigation region element.
   */
  function init(el) {
    primaryNavigationRegion = el;
    mobileNavigationButton = document.querySelector(
      '[data-drupal-selector="primary-nav__mobile-button"]',
    );
    secondLevelNavMenus = primaryNavigationRegion.querySelectorAll(
      '[data-drupal-selector="primary-nav-menu-item-has-children"]',
    );
    secondLevelToggleButtonSelector =
      '[data-drupal-selector="primary-nav-submenu-toggle-button"], button[data-drupal-selector="primary-nav-menu-link-has-children"]';

    initSecondLevelNav(secondLevelNavMenus);

    // Ensure that desktop submenus close when escape key is pressed.
    document.addEventListener('keyup', (e) => {
      if (e.key === 'Escape') {
        // Only close mobile navigation if all submenus are already closed.
        if (
          !primaryNavigationRegion.querySelector(
            `:is(${secondLevelToggleButtonSelector})[aria-expanded="true"]`,
          )
        ) {
          toggleMobileNav(false);
        }

        closeAllSubNav();
      }
    });

    document.body.addEventListener('keydown', focusTrap);

    // Close overlay on click.
    document
      .querySelector('[data-drupal-selector="primary-nav-overlay"]')
      .addEventListener('click', () => {
        toggleMobileNav(false);
      });

    // Remove overlays when browser is resized and desktop nav appears.
    window.addEventListener('resize', () => {
      toggleMobileNav(false);
      closeAllSubNav();
    });

    // Initialize the mobile menu button.
    mobileNavigationButton.setAttribute('aria-expanded', 'false');
    mobileNavigationButton.setAttribute(
      'aria-controls',
      primaryNavigationRegion.getAttribute('id'),
    );
    mobileNavigationButton.addEventListener(
      'click',
      handleMobileNavigationButtonClick,
    );

    // If user taps outside of menu, close all menus.
    document.addEventListener(
      'touchstart',
      (e) => {
        if (
          !e.target.matches(
            '[data-drupal-selector="primary-nav-menu--level-1"] *',
          )
        ) {
          closeAllSubNav();
        }
      },
      { passive: true },
    );

    // If hyperlink links to an anchor in the current page, close the
    // mobile menu after the click.
    primaryNavigationRegion.addEventListener('click', (e) => {
      if (
        e.target.matches(
          `[href*="${window.location.pathname}#"], [href*="${window.location.pathname}#"] *, [href^="#"], [href^="#"] *`,
        )
      ) {
        toggleMobileNav(false);
      }
    });
  }

  /**
   * Initialize the navigation.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attach context and settings for navigation.
   */
  Drupal.behaviors.primaryNav = {
    attach(context) {
      once(
        'primary-nav',
        '[data-drupal-selector="region-primary-navigation"]',
        context,
      ).forEach(init);
    },
  };
})(Drupal, once);
