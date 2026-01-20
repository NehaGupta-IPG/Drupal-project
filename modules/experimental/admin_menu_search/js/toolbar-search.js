(function (Drupal) {
  Drupal.behaviors.adminMenuSearch = {
    attach: function () {
      const input = document.getElementById('admin-menu-search-input');
      if (!input) {
        return;
      }

      input.addEventListener('input', function () {
        const value = this.value.toLowerCase();

        // Admin Toolbar + Core Toolbar links
        const links = document.querySelectorAll(
          '.toolbar-menu__link, .toolbar-menu a'
        );

        links.forEach(link => {
          const text = link.textContent.toLowerCase();
          const item =
            link.closest('.toolbar-menu__item') ||
            link.closest('li');

          if (!item) {
            return;
          }

          if (value === '' || text.includes(value)) {
            item.style.display = '';
          } else {
            item.style.display = 'none';
          }
        });
      });
    }
  };
})(Drupal);
