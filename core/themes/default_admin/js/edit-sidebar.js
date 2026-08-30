/**
 * @file
 * Interactions for resizable sidebar on edit pages.
 */

/* eslint-disable func-names, no-mutable-exports, comma-dangle, strict */
((Drupal, drupalSettings, once) => {
  const reSizer = document.getElementById('gin-sidebar-draggable');
  const resizable = document.getElementById('default_admin_sidebar');
  const storageWidth = "Drupal.admin.sidebarWidth";
  let isResizing = false;
  let startX, startWidth;

  Drupal.behaviors.sidebarResize = {
    attach: function attach() {
      Drupal.sidebarResize.init();
    },
  };

  Drupal.sidebarResize = {
    init: function () {
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
