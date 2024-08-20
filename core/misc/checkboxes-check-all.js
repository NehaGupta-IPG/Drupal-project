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

            const checkboxes = container.querySelectorAll(
              'input[type="checkbox"]',
            );
            let checkedAmount = 0;
            checkboxes.forEach(function (checkbox) {
              if (checkbox.checked) {
                checkedAmount++;
              }
            });
            // If not all are checked, we should check all.
            const checkAll = checkedAmount < checkboxes.length;
            checkboxes.forEach(function (checkbox) {
              checkbox.checked = checkAll;
            });
          });
        },
      );
    },
  };
})(Drupal, once);
