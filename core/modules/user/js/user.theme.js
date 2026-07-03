/**
 * @file
 * Theme elements for user password forms.
 */

((Drupal) => {
  /**
   * Constructs a password confirm message element.
   *
   * @param {object} passwordSettings
   *   An object containing password related settings and translated text to
   *   display.
   * @param {string} passwordSettings.confirmTitle
   *   The translated confirm description that labels the actual confirm text.
   *
   * @return {string}
   *   Markup for the password confirm message.
   */
  Drupal.theme.passwordConfirmMessage = ({ confirmTitle }) => {
    const confirmTextWrapper =
      '<span data-drupal-selector="password-match-status-text"></span>';
    return `<div aria-live="polite" aria-atomic="true" class="password-confirm-message" data-drupal-selector="password-confirm-message">${confirmTitle} ${confirmTextWrapper}</div>`;
  };

  /**
   * Constructs password suggestions tips.
   *
   * @param {object} passwordSettings
   *   An object containing password related settings and translated text to
   *   display.
   * @param {string} passwordSettings.hasWeaknesses
   *   The title that precedes tips.
   * @param {Array.<string>} tips
   *   Array containing the tips.
   *
   * @return {string}
   *   Markup for password suggestions.
   */
  Drupal.theme.passwordSuggestions = ({ hasWeaknesses }, tips) =>
    `<div class="password-suggestions">${
      tips.length
        ? `${hasWeaknesses}<ul><li>${tips.join('</li><li>')}</li></ul>`
        : ''
    }</div>`;
})(Drupal);
