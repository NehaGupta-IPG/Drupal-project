/**
 * @file
 * Builds a nested accordion widget.
 *
 * Invoke on an HTML list element with the jQuery plugin pattern.
 *
 * @example
 * $('.toolbar-menu').drupalToolbarMenu();
 */

(function ($, Drupal, drupalSettings, once, { focusable }) {
  /**
   * Store the open menu tray.
   */
  let activeItem = Drupal.url(drupalSettings.path.currentPath);

  /**
   * Maintains active tab in horizontal orientation.
   */
  $.fn.drupalToolbarMenuHorizontal = function () {
    let currentPath = drupalSettings.path.currentPath;
    const menu = once('toolbar-menu-horizontal', this);
    if (menu.length) {
      const $menu = $(menu);
      if (activeItem) {
        const count = currentPath.split('/').length;
        // Find the deepest link with its parent info and start
        // marking active.
        for (let i = 0; i < count; i++) {
          const $menuItem = $menu.find(
            `a[data-drupal-link-system-path="${currentPath}"]`,
          );
          if ($menuItem.length !== 0) {
            $menuItem.closest('a').addClass('is-active');
            break;
          }
          const lastIndex = currentPath.lastIndexOf('/');
          currentPath = currentPath.slice(0, lastIndex);
        }
      }
    }
  };

  $.fn.drupalToolbarMenu = function () {
    const ui = {
      handleOpen: Drupal.t('Extend'),
      handleClose: Drupal.t('Collapse'),
    };

    /**
     * Toggle the open/close state of a list is a menu.
     *
     * @param {jQuery} $item
     *   The li item to be toggled.
     *
     * @param {Boolean} switcher
     *   A flag that forces toggleClass to add or a remove a class, rather than
     *   simply toggling its presence.
     */
    function toggleList($item, switcher) {
      const $toggle = $item
        .children('.toolbar-box')
        .children('.toolbar-handle');
      switcher =
        typeof switcher !== 'undefined' ? switcher : !$item.hasClass('open');
      // Toggle the item open state.
      $item.toggleClass('open', switcher);
      // Twist the toggle.
      $toggle.toggleClass('open', switcher);
      // Adjust the toggle text.
      $toggle.find('.action').each((index, element) => {
        // Expand Structure, Collapse Structure.
        element.textContent = switcher ? ui.handleClose : ui.handleOpen;
      });
    }

    /**
     * Handle clicks from the disclosure button on an item with sub-items.
     *
     * @param {Object} event
     *   A jQuery Event object.
     */
    function toggleClickHandler(event) {
      const $toggle = $(event.target);
      const $item = $toggle.closest('li');
      // Toggle the list item.
      toggleList($item);
      // Close open sibling menus.
      const $openItems = $item.siblings().filter('.open');
      toggleList($openItems, false);
    }

    /**
     * Handle clicks from a menu item link.
     *
     * @param {Object} event
     *   A jQuery Event object.
     */
    function linkClickHandler(event) {
      // If the toolbar is positioned fixed (and therefore hiding content
      // underneath), then users expect clicks in the administration menu tray
      // to take them to that destination but for the menu tray to be closed
      // after clicking: otherwise the toolbar itself is obstructing the view
      // of the destination they chose.
      if (!Drupal.toolbar.models.toolbarModel.get('isFixed')) {
        Drupal.toolbar.models.toolbarModel.set('activeTab', null);
      }
      // Stopping propagation to make sure that once a toolbar-box is clicked
      // (the whitespace part), the page is not redirected anymore.
      event.stopPropagation();
    }

    /**
     * Add markup to the menu elements.
     *
     * Items with sub-elements have a list toggle attached to them. Menu item
     * links and the corresponding list toggle are wrapped with in a div
     * classed with .toolbar-box. The .toolbar-box div provides a positioning
     * context for the item list toggle.
     *
     * @param {jQuery} $menu
     *   The root of the menu to be initialized.
     */
    function initItems($menu) {
      const options = {
        class: 'toolbar-icon toolbar-handle',
        action: ui.handleOpen,
        text: '',
      };
      // Initialize items and their links.
      $menu.find('li > a').wrap('<div class="toolbar-box">');
      // Add a handle to each list item if it has a menu.
      $menu.find('li').each((index, element) => {
        const $item = $(element);
        if ($item.children('ul.toolbar-menu').length) {
          const $box = $item.children('.toolbar-box');
          const $link = $box.find('a');
          options.text = Drupal.t('@label', {
            '@label': $link.length ? $link[0].textContent : '',
          });
          $item
            .children('.toolbar-box')
            .append(
              $(Drupal.theme('toolbarMenuItemToggle', options))
                .hide()
                .fadeIn(150),
            );
        }
      });
    }

    /**
     * Adds a level class to each list based on its depth in the menu.
     *
     * This function is called recursively on each sub level of lists elements
     * until the depth of the menu is exhausted.
     *
     * @param {jQuery} $lists
     *   A jQuery object of ul elements.
     *
     * @param {number} level
     *   The current level number to be assigned to the list elements.
     */
    function markListLevels($lists, level) {
      level = !level ? 1 : level;
      const $lis = $lists.children('li').addClass(`level-${level}`);
      $lists = $lis.children('ul');
      if ($lists.length) {
        markListLevels($lists, level + 1);
      }
    }

    /**
     * On page load, open the active menu item.
     *
     * Marks the trail of the active link in the menu back to the root of the
     * menu with .menu-item--active-trail.
     *
     * @param {jQuery} $menu
     *   The root of the menu.
     */
    function openActiveItem($menu) {
      let currentPath = drupalSettings.path.currentPath;
      const pathItem = $menu.find(`a[href="${window.location.pathname}"]`);
      if (pathItem.length && !activeItem) {
        activeItem = window.location.pathname;
      }
      if (activeItem) {
        const $activeItem = $menu
          .find(`a[href="${activeItem}"]`)
          .addClass('menu-item--active');
        if (pathItem.length === 0 && activeItem) {
          const count = currentPath.split('/').length;
          // Find the deepest link with its parent info and start
          // marking active.
          for (let i = 0; i < count; i++) {
            const $menuItem = $menu.find(
              `a[data-drupal-link-system-path="${currentPath}"]`,
            );
            if ($menuItem.length !== 0) {
              const $activeTrail = $menuItem
                .parentsUntil('.root', 'li')
                .addClass('menu-item--active-trail');
              toggleList($activeTrail, true);
              break;
            }
            const lastIndex = currentPath.lastIndexOf('/');
            currentPath = currentPath.slice(0, lastIndex);
          }
        } else {
          const $activeTrail = $activeItem
            .parentsUntil('.root', 'li')
            .addClass('menu-item--active-trail');
          toggleList($activeTrail, true);
        }
      }
    }

    // Return the jQuery object.
    return this.each(function (selector) {
      const menu = once('toolbar-menu-vertical', this);
      if (menu.length) {
        const $menu = $(menu);
        // Bind event handlers.
        $menu
          .on('click.toolbar', '.toolbar-box', toggleClickHandler)
          .on('click.toolbar', '.toolbar-box a', linkClickHandler);

        $menu.addClass('root');
        initItems($menu);
        markListLevels($menu);
        // Restore previous and active states.
        openActiveItem($menu);
      }
    });
  };

  Drupal.behaviors.toolbarNavigate = {
    attach(context, settings) {
      // Add data attributes to toolbar items. These are in several once() calls
      // to ensure lazy builder content is accounted for.

      // Every menu link gets 'data-toolbar-item'.
      once('toolbar-menu-item', '.menu-item > a', context).forEach(
        (menuItemLink) => {
          menuItemLink.setAttribute('data-toolbar-item', '');
        },
      );
      once(
        'toolbar-menu-top-item',
        'nav > div >.toolbar-menu',
        context,
      ).forEach((topMenu) => {
        topMenu.querySelectorAll(':scope > li').forEach((li) => {
          li.setAttribute('data-toolbar-top-wrapper', '');
        });
      });

      // Every menu link in a submenu gets 'data-toolbar-sub-item'.
      once(
        'toolbar-menu-sub-item',
        '.menu-item .menu-item > a',
        context,
      ).forEach((menuItemLink) => {
        menuItemLink.setAttribute('tabindex', '-1');
        menuItemLink.setAttribute(
          'data-toolbar-sub-item',
          $(menuItemLink).parents('.menu-item').length,
        );

        // Add data attributes for the first and last items in the menu.
        if (!menuItemLink.parentElement.previousElementSibling) {
          menuItemLink.setAttribute('data-toolbar-item-first', '');
        }
        if (!menuItemLink.parentElement.nextElementSibling) {
          menuItemLink.setAttribute('data-toolbar-item-last', '');
        }
      });

      // Add a keydown listener to the entire toolbar.
      once('toolbar-key-map', '#toolbar-bar').forEach((toolbar) => {
        const toolbarKeydown = (e) => {
          // These keyboard commands are only intended for the horizontal
          // toolbar.
          if (!document.body.classList.contains('toolbar-horizontal')) {
            return;
          }

          // Escape key moves focus out of submenu items.
          if (
            e.key === 'Escape' &&
            e.target.hasAttribute('data-toolbar-sub-item')
          ) {
            // Escape moves focus to the top level menu item but does not close
            // the menu as the menu automatically opens on focus.
            e.target
              .closest('[data-toolbar-top-wrapper]')
              .querySelector('a')
              .focus();
          }

          // Exit early if the key pressed is not an arrow key or the event
          // target is not a menu link.
          if (
            !e.target.hasAttribute('data-toolbar-item') ||
            !e.key.includes('Arrow')
          ) {
            return;
          }

          e.preventDefault();
          e.stopPropagation();

          // This is every focusable element within the menu the target belongs
          // to, including submenus.
          const focusablePeers = focusable(
            e.target.parentElement.parentElement,
          );

          // This removes the submenu items from focusablePeers.
          const focusableSiblings = focusablePeers.filter(
            (element) =>
              e.target.getAttribute('data-toolbar-sub-item') ===
              element.getAttribute('data-toolbar-sub-item'),
          );

          // The position of the event target within the menu.
          const currentIndex = focusableSiblings.indexOf(e.target);

          // Logic that is only applied to top level menu items.
          if (!e.target.hasAttribute('data-toolbar-sub-item')) {
            // Pressing down on a top level menu item with a submenu will move
            // focus to the first item within that submenu.
            if (
              e.target.parentElement.classList.contains(
                'menu-item--expanded',
              ) &&
              e.key === 'ArrowDown'
            ) {
              e.preventDefault();
              e.target.parentElement
                .querySelector('.toolbar-menu .toolbar-menu > .menu-item > a')
                .focus();
              return;
            }

            // The left and right arrow keys will move focus through the top
            // level items.
            if (e.key === 'ArrowLeft' && currentIndex !== 0) {
              focusableSiblings[currentIndex - 1].focus();
            }
            if (
              e.key === 'ArrowRight' &&
              currentIndex !== focusableSiblings.length - 1
            ) {
              focusableSiblings[currentIndex + 1].focus();
            }
          }

          // This logic is specific to submenu items.
          if (e.target.hasAttribute('data-toolbar-sub-item')) {
            switch (e.key) {
              case 'ArrowDown':
                // Move focus to the next item when available.
                if (!e.target.hasAttribute('data-toolbar-item-last')) {
                  focusableSiblings[currentIndex + 1].focus();
                }
                break;
              case 'ArrowUp':
                // Move focus to the prior item when available.
                if (!e.target.hasAttribute('data-toolbar-item-first')) {
                  focusableSiblings[currentIndex - 1].focus();
                } else if (
                  e.target.getAttribute('data-toolbar-sub-item') === '2'
                ) {
                  // On a 2nd level menu item, pressing up on the first item
                  // should move focus to the parent menu.
                  e.target
                    .closest('[data-toolbar-top-wrapper]')
                    .querySelector('a')
                    .focus();
                }
                break;
              case 'ArrowRight':
                // If the right arrow is pressed on an item that has a submenu,
                // move focus to the first item in that submenu.
                if (
                  e.target.parentElement.classList.contains(
                    'menu-item--expanded',
                  )
                ) {
                  const itemLevel = parseInt(
                    e.target.getAttribute('data-toolbar-sub-item'),
                    10,
                  );
                  e.target.parentElement
                    .querySelector(`[data-toolbar-sub-item="${itemLevel + 1}"]`)
                    .focus();
                }
                break;
              case 'ArrowLeft':
                if (e.target.hasAttribute('data-toolbar-item-first')) {
                  const itemLevel = parseInt(
                    e.target.getAttribute('data-toolbar-sub-item'),
                    10,
                  );
                  // If the left arrow is pressed on the first item in a submenu
                  // at level 3 or higher, move focus to the parent menu item.
                  if (itemLevel >= 3) {
                    e.target.parentElement.parentElement
                      .closest('.menu-item--expanded')
                      .querySelector('a')
                      .focus();
                  }
                }
                break;
              default:
                break;
            }
          }
        };
        toolbar.addEventListener('keydown', toolbarKeydown);
      });

      // Make the toolbar menu navigable with keyboard.
      $('ul.toolbar-menu li.menu-item--expanded a', context).on(
        'focusin',
        // eslint-disable-next-line func-names
        function () {
          $('li.menu-item--expanded', context).removeClass('hover-intent');
          $(this).parents('li.menu-item--expanded').addClass('hover-intent');
        },
      );

      $('ul.toolbar-menu li.menu-item a', context).keydown(function (e) {
        if (e.shiftKey && (e.keyCode || e.which) === 9) {
          if (
            $(this).parent('.menu-item').prev().hasClass('menu-item--expanded')
          ) {
            $(this).parent('.menu-item').prev().addClass('hover-intent');
          }
        }
      });

      $(
        '.toolbar-menu:first-child > .menu-item:not(.menu-item--expanded) a, .toolbar-tab > a',
        context,
      ).on('focusin', () => {
        $('.menu-item--expanded').removeClass('hover-intent');
      });

      $('.toolbar-menu:first-child > .menu-item', context).on(
        'hover',
        // eslint-disable-next-line func-names
        function () {
          $(this, 'a').css('background: #fff;');
        },
      );

      $('ul:not(.toolbar-menu)', context).on({
        mousemove() {
          $('li.menu-item--expanded').removeClass('hover-intent');
        },
        hover() {
          $('li.menu-item--expanded').removeClass('hover-intent');
        },
      });

      // Always hide the dropdown menu on mobile.
      if (
        window.matchMedia('(max-width: 767px)').matches &&
        $('body').hasClass('toolbar-tray-open')
      ) {
        $('body').removeClass('toolbar-tray-open');
        $('#toolbar-item-administration').removeClass('is-active');
        $('#toolbar-item-administration-tray').removeClass('is-active');
      }
    },
  };

  /**
   * A toggle is an interactive element often bound to a click handler.
   *
   * @param {object} options
   *   Options for the button.
   * @param {string} options.class
   *   Class to set on the button.
   * @param {string} options.action
   *   Action for the button.
   * @param {string} options.text
   *   Used as label for the button.
   *
   * @return {string}
   *   A string representing a DOM fragment.
   */
  Drupal.theme.toolbarMenuItemToggle = function (options) {
    return `<button class="${options.class}"><span class="action">${options.action}</span> <span class="label">${options.text}</span></button>`;
  };
})(jQuery, Drupal, drupalSettings, once, window.tabbable);
