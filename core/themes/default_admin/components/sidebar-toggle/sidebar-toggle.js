/**
 * @file
 * Interactions for collapsible sidebar on edit pages.
 */

/* eslint-disable func-names, no-mutable-exports, comma-dangle, strict */
((Drupal, drupalSettings, once) => {
  const breakpoint = 1024;
  const breakpointLarge = 1280;
  const storageMobile = 'Drupal.admin.sidebarExpanded.mobile';
  const storageDesktop = 'Drupal.admin.sidebarExpanded.desktop';

  Drupal.behaviors.adminSidebar = {
    attach: function attach(context) {
      Drupal.adminSidebar.init(context);
    },
  };

  Drupal.adminSidebar = {
    init: function (context) {
      once('adminSidebarInit', '#default_admin_sidebar', context).forEach(() => {
        // If variable does not exist, create it, default being to show sidebar.
        if (!localStorage.getItem(storageDesktop)) {
          localStorage.setItem(storageDesktop, 'true');
        }

        // Set mobile initial to false.
        if (window.innerWidth >= breakpoint) {
          if (localStorage.getItem(storageDesktop) === 'true') {
            this.showSidebar();
          }
          else {
            this.collapseSidebar();
          }
        }

        // Resize observer.
        const resizeHandler = new ResizeObserver(entries => {
          for (let entry of entries) {
            Drupal.debounce(this.handleResize(entry.contentRect), 150);
          }
        });
        resizeHandler.observe(document.querySelector('html'));
      });

      // Toolbar toggle
      once('adminSidebarToggle', '.meta-sidebar__trigger', context).forEach(el => el.addEventListener('click', e => {
        this.toggleSidebar();
      }));

      // Toolbar close
      once('adminSidebarClose', '.meta-sidebar__close, .meta-sidebar__overlay', context).forEach(el => el.addEventListener('click', e => {
        this.collapseSidebar();
      }));
    },

    toggleSidebar: () => {
      // Set active state.
      if (document.querySelector('.meta-sidebar__trigger').classList.contains('is-active')) {
        Drupal.adminSidebar.collapseSidebar();
        Drupal.adminStickyFormActions?.hideMoreActions();
      }
      else {
        Drupal.adminSidebar.showSidebar();
        Drupal.adminStickyFormActions?.hideMoreActions();
      }
    },

    showSidebar: () => {
      const chooseStorage = window.innerWidth < breakpoint ? storageMobile : storageDesktop;
      const sidebarTrigger = document.querySelector('.meta-sidebar__trigger');

      sidebarTrigger?.setAttribute('aria-expanded', 'true');
      sidebarTrigger?.classList.add('is-active');
      document.body.setAttribute('data-meta-sidebar', 'open');

      // Expose to localStorage.
      localStorage.setItem(chooseStorage, 'true');

      // Check which toolbar is active.
      if (window.innerWidth < breakpointLarge) {
        Drupal.adminCoreNavigation?.collapseToolbar();
      }
    },

    collapseSidebar: () => {
      const chooseStorage = window.innerWidth < breakpoint ? storageMobile : storageDesktop;
      const sidebarTrigger = document.querySelector('.meta-sidebar__trigger');

      sidebarTrigger?.setAttribute('aria-expanded', 'false');
      sidebarTrigger?.classList.remove('is-active');
      document.body.setAttribute('data-meta-sidebar', 'closed');

      // Expose to localStorage.
      localStorage.setItem(chooseStorage, 'false');
    },

    handleResize: (windowSize = window) => {
      // If small viewport, always collapse sidebar.
      if (windowSize.width < breakpoint) {
        Drupal.adminSidebar.collapseSidebar();
      } else {
        // If large viewport, show sidebar if it was open before.
        if (localStorage.getItem(storageDesktop) === 'true') {
          Drupal.adminSidebar.showSidebar();
        } else {
          Drupal.adminSidebar.collapseSidebar();
        }
      }
    }
  };
})(Drupal, drupalSettings, once);
