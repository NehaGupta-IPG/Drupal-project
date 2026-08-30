/* eslint-disable func-names, no-mutable-exports, comma-dangle, strict */

((Drupal, drupalSettings, once) => {
  const breakpoint = 1024;
  const breakpointLarge = 1280;
  const storageMobile = 'Drupal.admin.sidebarExpanded.mobile';
  const storageDesktop = 'Drupal.admin.sidebarExpanded.desktop';
  const storageWidth = "Drupal.admin.sidebarWidth";
  const reSizer = document.getElementById('gin-sidebar-draggable');
  const resizable = document.getElementById('default_admin_sidebar');
  let isResizing = false;
  let startX, startWidth;

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

        // Init resizable sidebar.
        this.resizeInit();
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
    },

    resizeInit: function () {
      // Mouse
      reSizer.addEventListener('mousedown', this.resizeStart);
      document.addEventListener('mousemove', this.resizeWidth);
      document.addEventListener('mouseup', this.resizeEnd);

      // Touch
      reSizer.addEventListener('touchstart', this.resizeStart);
      document.addEventListener('touchmove', this.resizeWidth);
      document.addEventListener('touchend', this.resizeEnd);
    },

    resizeStart: (e) => {
      e.preventDefault();
      isResizing = true;
      startX = e.clientX;
      startWidth = parseInt(document.defaultView.getComputedStyle(resizable).width, 10);
    },

    resizeEnd: () => {
      isResizing = false;
      const setWidth = document.documentElement.style.getPropertyValue('--admin-theme-sidebar-width');
      const currentWidth = setWidth ? setWidth : resizable.style.width;
      localStorage.setItem(storageWidth, currentWidth);
      document.removeEventListener('mousemove', this.resizeWidth);
      document.removeEventListener('touchend', this.resizeWidth);
    },

    resizeWidth: (e) => {
      if (isResizing) {
        let sidebarWidth = startWidth - (e.clientX - startX);

        if (sidebarWidth <= 240) {
          sidebarWidth = 240;
        } else if (sidebarWidth >= 560) {
          sidebarWidth = 560;
        }

        sidebarWidth = `${sidebarWidth}px`;
        // resizable.style.width = sidebarWidth;
        document.documentElement.style.setProperty('--admin-theme-sidebar-width', sidebarWidth);
      }
    }

  };
})(Drupal, drupalSettings, once);
