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
          const checked = context.querySelector(
            '[class*="js-form-item-comment"] input:checked',
          );
          const label = checked
            ? context.querySelector(`label[for="${checked.id}"]`)
            : null;
          return label != null ? Drupal.checkPlain(label.textContent) : '';
        });
    },
  };
})(jQuery, Drupal);
