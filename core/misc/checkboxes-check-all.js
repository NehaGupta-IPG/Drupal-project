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
      once('check-all', '.form-checkboxes__check-all', context).forEach(
        (wrapper) => {
          const container = wrapper.closest('.form-checkboxes');
          if (!container) return;

          const selectAll = document.createElement('input');
          selectAll.type = 'checkbox';
          selectAll.className = 'check-all';

          const label = document.createElement('label');
          label.className = 'form-checkboxes__check-all-label';
          label.append(selectAll, Drupal.t('Select all'));

          wrapper.appendChild(label);

          const getCheckboxes = () => [
            ...container.querySelectorAll(
              'input[type="checkbox"]:not(.check-all)',
            ),
          ];

          selectAll.addEventListener('change', () => {
            getCheckboxes().forEach((cb) => {
              cb.checked = selectAll.checked;
            });
          });

          // Keep the select-all state in sync when individual checkboxes change.
          container.addEventListener('change', (e) => {
            if (
              e.target === selectAll ||
              !e.target.matches('input[type="checkbox"]')
            )
              return;
            const checkboxes = getCheckboxes();
            selectAll.checked = checkboxes.every((cb) => cb.checked);
          });
        },
      );
    },
  };
})(Drupal, once);
