(function ($) {
  setTimeout(() => {
    $(
      '.toolbar-tray.toolbar-tray-horizontal .menu-item.menu-item--expanded',
    ).hover(
      // eslint-disable-next-line func-names
      function () {
        // At the current depth, we should delete all "hover-intent" classes.
        // Otherwise, we get unwanted behaviour where menu items are expanded while already in hovering other ones.
        $(this).parent().find('li').removeClass('hover-intent');
        $(this).addClass('hover-intent');
      },
      // eslint-disable-next-line func-names
      function () {
        $(this).removeClass('hover-intent');
      },
    );
  });
})(jQuery);
