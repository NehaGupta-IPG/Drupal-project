/**
 * @file
 * Attaches comment behaviors to the entity form.
 */

(function ($, Drupal) {
  /**
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.commentFieldsetSummaries = {
    attach(context) {
      const $context = $(context);
      $context
        .find('.comment-settings-form')
        .drupalSetSummary((context) =>
          Drupal.checkPlain(
            $(context).find('input[name$="[status]"]:checked').next('label')[0]
              .textContent,
          ),
        );
    },
  };
})(jQuery, Drupal);
