/**
 * @file
 * Attaches comment behaviors to the entity form.
 */

(function ($, Drupal) {
  /**
   * Provide the summary information for the comment settings vertical tab.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches summary behavior for comment settings.
   */
  Drupal.behaviors.commentFieldsetSummaries = {
    attach(context) {
      const $context = $(context);
      $context
        .find('[class*="comment-"][class*="-settings-form"]')
        .drupalSetSummary((context) => {
          // Support both field API and base comment status input names.
          // Comment fields added via the field API versus those "built-in"
          // i.e., with the standard install profile have slightly different
          // markup
          const checked = context.querySelector(
            'input[type="radio"][name^="field_comment"][name$="[status]"]:checked, input[type="radio"][name^="comment"][name$="[status]"]:checked',
          );

          if (!checked) {
            return '';
          }

          const label = context.querySelector(`label[for="${checked.id}"]`);
          return label ? Drupal.checkPlain(label.textContent.trim()) : 'Empty';
        });

    },
  };
})(jQuery, Drupal);
