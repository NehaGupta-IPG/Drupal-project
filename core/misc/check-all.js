/**
 * @file
 * Add possibility to check all child items element.
 */
(function (Drupal, once) {
  /**
   * Handles multiple checkboxes check/uncheck all elements.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.checkAll = {
    attach(context) {
      once('check-all-btn', '.form-checkboxes', context).forEach(
        (container) => {
          container.addEventListener('click', function (e) {
            const clickedBtn = e.target.closest('.check-all-btn');

            if (!clickedBtn) return;

            const checkboxes = container.querySelectorAll(
              'input[type="checkbox"]',
            );
            const checkAll = typeof clickedBtn.dataset.checkAll !== 'undefined';
            checkboxes.forEach(function (checkbox) {
              checkbox.checked = checkAll;
            });
          });
        },
      );
    },
  };
})(Drupal, once);
