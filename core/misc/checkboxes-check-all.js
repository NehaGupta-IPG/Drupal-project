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
      once('button--check-all', '.form-checkboxes', context).forEach(
        (container) => {
          container.addEventListener('click', function (e) {
            const clickedBtn = e.target.closest('.button--check-all');

            if (!clickedBtn) return;

            const checkboxes = [
              ...container.querySelectorAll('input[type="checkbox"]'),
            ];
            // If not all are checked, check all; otherwise uncheck all.
            const checkAll = checkboxes.some((cb) => !cb.checked);
            checkboxes.forEach((cb) => {
              cb.checked = checkAll;
            });
          });
        },
      );
    },
  };
})(Drupal, once);
