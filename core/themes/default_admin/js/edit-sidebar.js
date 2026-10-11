/**
 * @file
 * Interactions for resizable sidebar on edit pages.
 */

((Drupal, once) => {
  const storageWidth = 'Drupal.defaultAdmin.sidebarWidth';
  let isResizing = false;
  let resizable, startX, startWidth;

  // Touch events have no clientX of their own; read it from the first touch.
  const getClientX = (e) => (e.touches ? e.touches[0].clientX : e.clientX);

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
      // Touch scrolling from the handle is blocked by `touch-action: none` in
      // CSS. Chrome marks touchstart as not cancelable while a previous scroll
      // is still in progress, and calling preventDefault() then only logs an
      // intervention warning.
      if (e.cancelable) {
        e.preventDefault();
      }
      isResizing = true;
      resizable = e.currentTarget.closest('#default_admin_sidebar');
      startX = getClientX(e);
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
        let sidebarWidth = startWidth - (getClientX(e) - startX);

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
