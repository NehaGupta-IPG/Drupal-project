/**
 * @file
 * Interactions for collapsible sidebar on edit pages.
 */

/* eslint-disable func-names, comma-dangle, strict */
((Drupal, once) => {
  // Matches the `--admin-large` custom media query, and the check in
  // migration/js/init.js that sets the initial sidebar state.
  const largeQuery = window.matchMedia('(min-width: 64em)');
  // Matches the `--admin-wide` custom media query.
  const wideQuery = window.matchMedia('(min-width: 80em)');
  const storageMobile = 'Drupal.defaultAdmin.sidebarExpanded.mobile';
  const storageDesktop = 'Drupal.defaultAdmin.sidebarExpanded.desktop';
  // Elements that stay interactive while the sidebar overlays the page.
  const keepInteractive = '.meta-sidebar__overlay, [aria-live]';
  // Elements made inert while the sidebar overlays the page, and the element
  // that had focus before it opened.
  let inertElements = [];
  let returnFocusTo = null;

  /**
   * Makes an element inert, unless it is or contains one that must stay usable.
   *
   * @param {HTMLElement} el
   *   The element to make inert.
   */
  const makeInert = (el) => {
    if (el.inert || el.matches(`script, ${keepInteractive}`)) {
      return;
    }
    // An inert element's descendants can't be re-enabled, so descend instead.
    if (el.querySelector(keepInteractive)) {
      [...el.children].forEach(makeInert);
      return;
    }
    el.inert = true;
    inertElements.push(el);
  };

  Drupal.behaviors.adminSidebar = {
    attach: function attach(context) {
      Drupal.adminSidebar.init(context);
    },
  };

  Drupal.adminSidebar = {
    init: function (context) {
      once('adminSidebarInit', '#default_admin_sidebar', context).forEach(
        () => {
          // If variable does not exist, create it, default being to show sidebar.
          if (!localStorage.getItem(storageDesktop)) {
            localStorage.setItem(storageDesktop, 'true');
          }

          // Sync the toggle with the initial state, then re-run only when the
          // viewport crosses the breakpoint.
          this.handleResize();
          largeQuery.addEventListener('change', () => this.handleResize());

          // Escape closes the sidebar while it overlays the page. Handlers
          // inside the sidebar, such as autocomplete, prevent the default
          // when they use Escape themselves.
          document.addEventListener('keydown', (e) => {
            if (
              e.key === 'Escape' &&
              !e.defaultPrevented &&
              inertElements.length
            ) {
              this.collapseSidebar();
            }
          });
        },
      );

      // Toolbar toggle
      once('adminSidebarToggle', '.meta-sidebar__trigger', context).forEach(
        (el) =>
          el.addEventListener('click', () => {
            this.toggleSidebar();
          }),
      );

      // Toolbar close
      once(
        'adminSidebarClose',
        '.meta-sidebar__close, .meta-sidebar__overlay',
        context,
      ).forEach((el) =>
        el.addEventListener('click', () => {
          this.collapseSidebar();
        }),
      );
    },

    toggleSidebar: () => {
      // Set active state.
      if (
        document
          .querySelector('.meta-sidebar__trigger')
          .classList.contains('is-active')
      ) {
        Drupal.adminSidebar.collapseSidebar();
        Drupal.adminStickyFormActions?.hideMoreActions();
      } else {
        Drupal.adminSidebar.showSidebar();
        Drupal.adminStickyFormActions?.hideMoreActions();
      }
    },

    showSidebar: () => {
      const chooseStorage = largeQuery.matches ? storageDesktop : storageMobile;
      const sidebarTrigger = document.querySelector('.meta-sidebar__trigger');

      sidebarTrigger?.setAttribute('aria-expanded', 'true');
      sidebarTrigger?.classList.add('is-active');
      // State lives on <html> because init.js sets the initial value from
      // <head>, before <body> exists and before first paint.
      document.documentElement.setAttribute('data-meta-sidebar', 'open');

      // Expose to localStorage.
      localStorage.setItem(chooseStorage, 'true');

      // Check which toolbar is active.
      if (!wideQuery.matches) {
        Drupal.adminCoreNavigation?.collapseToolbar();
      }

      // Below the large breakpoint the sidebar overlays the page content.
      if (largeQuery.matches) {
        Drupal.adminSidebar.restoreCoveredContent(false);
      } else {
        Drupal.adminSidebar.inertCoveredContent();
      }
    },

    collapseSidebar: () => {
      const chooseStorage = largeQuery.matches ? storageDesktop : storageMobile;
      const sidebarTrigger = document.querySelector('.meta-sidebar__trigger');

      sidebarTrigger?.setAttribute('aria-expanded', 'false');
      sidebarTrigger?.classList.remove('is-active');
      document.documentElement.setAttribute('data-meta-sidebar', 'closed');

      // Expose to localStorage.
      localStorage.setItem(chooseStorage, 'false');

      Drupal.adminSidebar.restoreCoveredContent(true);
    },

    inertCoveredContent: () => {
      if (inertElements.length) {
        return;
      }
      const sidebar = document.getElementById('default_admin_sidebar');
      if (!sidebar) {
        return;
      }

      // Make the content under the overlay inert, so keyboard and assistive
      // technology users can't reach it. Walk up from the sidebar and make the
      // siblings at each level inert, stopping at the main canvas. Navigation
      // and the top bar are outside it and are not covered by the overlay, so
      // they stay interactive.
      const boundary =
        sidebar.closest('.dialog-off-canvas-main-canvas') || document.body;
      let node = sidebar;
      while (node !== boundary && node.parentElement) {
        [...node.parentElement.children].forEach((sibling) => {
          if (sibling !== node) {
            makeInert(sibling);
          }
        });
        node = node.parentElement;
      }

      // Move focus into the sidebar. It comes before Navigation and the top
      // bar in the DOM, so tabbing continues from the sidebar to those.
      returnFocusTo = document.activeElement;
      (sidebar.querySelector('.meta-sidebar__close') || sidebar).focus();
    },

    restoreCoveredContent: (returnFocus) => {
      if (!inertElements.length) {
        return;
      }
      inertElements.forEach((el) => {
        el.inert = false;
      });
      inertElements = [];

      // Return focus to where it was before the sidebar opened, normally the
      // toggle, if focus was in the now hidden sidebar.
      const sidebar = document.getElementById('default_admin_sidebar');
      if (
        returnFocus &&
        returnFocusTo?.isConnected &&
        (sidebar?.contains(document.activeElement) ||
          document.activeElement === document.body)
      ) {
        returnFocusTo.focus();
      }
      returnFocusTo = null;
    },

    handleResize: () => {
      // If small viewport, always collapse sidebar.
      if (!largeQuery.matches) {
        Drupal.adminSidebar.collapseSidebar();
      } else {
        // If large viewport, show sidebar if it was open before.
        if (localStorage.getItem(storageDesktop) === 'true') {
          Drupal.adminSidebar.showSidebar();
        } else {
          Drupal.adminSidebar.collapseSidebar();
        }
      }
    },
  };
})(Drupal, once);
