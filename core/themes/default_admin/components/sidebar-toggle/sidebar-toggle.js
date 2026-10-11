/**
 * @file
 * Interactions for collapsible sidebar on edit pages.
 */

/* eslint-disable func-names, comma-dangle, strict */
((Drupal, once) => {
  // Matches the `--admin-large` custom media query, and the check in
  // migration/js/init.js that sets the initial sidebar state.
  const largeQuery = window.matchMedia('(min-width: 64em)');
  const breakpointLarge = 1280;
  const storageMobile = 'Drupal.defaultAdmin.sidebarExpanded.mobile';
  const storageDesktop = 'Drupal.defaultAdmin.sidebarExpanded.desktop';

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
          // viewport crosses the breakpoint. Observing size changes instead
          // also fires when page content grows or shrinks, which re-opened the
          // sidebar and collapsed the Navigation toolbar below 1280px.
          this.handleResize();
          largeQuery.addEventListener('change', () => this.handleResize());
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
        el.addEventListener('click', (e) => {
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
      if (window.innerWidth < breakpointLarge) {
        Drupal.adminCoreNavigation?.collapseToolbar();
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
