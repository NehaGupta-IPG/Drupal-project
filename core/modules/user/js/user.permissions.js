/**
 * @file
 * User permission page behaviors.
 */

(function ($, Drupal) {
  /**
   * Shows checked and disabled checkboxes for inherited permissions.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches functionality to the permissions table.
   */
  Drupal.behaviors.permissions = {
    attach() {
      const [table] = once('permissions', 'table#permissions');
      if (!table) {
        return;
      }

      // Create fake checkboxes. We use fake checkboxes instead of reusing
      // the existing checkboxes here because new checkboxes don't alter the
      // submitted form. If we'd automatically check existing checkboxes, the
      // permission table would be polluted with redundant entries. This is
      // deliberate, but desirable when we automatically check them.
      const $fakeCheckbox = $(Drupal.theme('checkbox'))
        .removeClass('form-checkbox')
        .addClass('fake-checkbox js-fake-checkbox')
        .attr({
          disabled: 'disabled',
          checked: 'checked',
          title: Drupal.t(
            'This permission is inherited from the authenticated user role.',
          ),
        });
      const $wrapper = $('<div></div>').append($fakeCheckbox);
      const fakeCheckboxHtml = $wrapper.html();

      /**
       * Process each table row to create fake checkboxes.
       *
       * @param {object} object
       * @param {HTMLElement} object.target
       */
      function tableRowProcessing({ target }) {
        once('permission-checkbox', target).forEach((checkbox) => {
          checkbox
            .closest('tr')
            .querySelectorAll(
              'input[type="checkbox"]:not(.js-rid-anonymous, .js-rid-authenticated)',
            )
            .forEach((check) => {
              check.classList.add('real-checkbox', 'js-real-checkbox');
              check.insertAdjacentHTML('beforebegin', fakeCheckboxHtml);
            });
        });
      }

      // An IntersectionObserver object is associated with each of the table
      // rows to activate checkboxes interactively as users scroll the page
      // up or down. This prevents processing all checkboxes on page load.
      const checkedCheckboxObserver = new IntersectionObserver(
        (entries, thisObserver) => {
          entries
            .filter((entry) => entry.isIntersecting)
            .forEach((entry) => {
              tableRowProcessing(entry);
              thisObserver.unobserve(entry.target);
            });
        },
        {
          rootMargin: '50%',
        },
      );

      // Select rows with checked authenticated role and attach an observer
      // to each.
      table
        .querySelectorAll(
          'tbody tr input[type="checkbox"].js-rid-authenticated:checked',
        )
        .forEach((checkbox) => checkedCheckboxObserver.observe(checkbox));

      // Create checkboxes only when necessary on click.
      $(table).on(
        'click.permissions',
        'input[type="checkbox"].js-rid-authenticated',
        tableRowProcessing,
      );
    },
  };
})(jQuery, Drupal);
