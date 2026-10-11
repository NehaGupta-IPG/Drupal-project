/**
 * @file
 * Interactions for resizable sidebar on edit pages.
 */

((Drupal, once) => {
  const storageWidth = 'Drupal.defaultAdmin.sidebarWidth';
  let isResizing = false;
  let resizable, startX, startWidth;

  Drupal.behaviors.sidebarResize = {
    attach: function attach(context) {
      Drupal.sidebarResize.init(context);
    },
  };

  Drupal.sidebarResize = {
    init: function (context) {
      // Look up the handle on each attach rather than once at script load, so
      // a sidebar inserted or re-rendered by AJAX still gets wired up.
      once('sidebarResize', '#sidebar-draggable', context).forEach(
        (reSizer) => {
          reSizer.addEventListener('mousedown', this.resizeStart);
          reSizer.addEventListener('touchstart', this.resizeStart);
        },
      );

      // Move and end are tracked on the document so the drag continues when
      // the pointer leaves the handle. They only need adding once per page.
      once('sidebarResize', 'html').forEach(() => {
        document.addEventListener('mousemove', this.resizeWidth);
        document.addEventListener('mouseup', this.resizeEnd);
        document.addEventListener('touchmove', this.resizeWidth);
        document.addEventListener('touchend', this.resizeEnd);
      });
    },

    resizeStart: (e) => {
      e.preventDefault();
      isResizing = true;
      resizable = e.currentTarget.closest('#default_admin_sidebar');
      startX = e.clientX;
      startWidth = parseInt(
        document.defaultView.getComputedStyle(resizable).width,
        10,
      );
    },

    resizeEnd: () => {
      if (!isResizing) {
        return;
      }
      isResizing = false;
      const setWidth = document.documentElement.style.getPropertyValue(
        '--admin-theme-sidebar-width',
      );
      const currentWidth = setWidth ? setWidth : resizable.style.width;
      localStorage.setItem(storageWidth, currentWidth);
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
        document.documentElement.style.setProperty(
          '--admin-theme-sidebar-width',
          sidebarWidth,
        );
      }
    },
  };
})(Drupal, once);
