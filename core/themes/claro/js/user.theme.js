/**
 * @file
 * Password confirm widget template overrides.
 */

((Drupal) => {
  Object.assign(Drupal.user.password.css, {
    passwordWeak: 'is-weak',
    widgetInitial: 'is-initial',
    passwordEmpty: 'is-password-empty',
    passwordFilled: 'is-password-filled',
    confirmEmpty: 'is-confirm-empty',
    confirmFilled: 'is-confirm-filled',
  });

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
      '<span class="password-match-message__text" data-drupal-selector="password-match-status-text"></span>';
    return `<div aria-live="polite" aria-atomic="true" class="password-match-message" data-drupal-selector="password-confirm-message">${confirmTitle} ${confirmTextWrapper}</div>`;
  };

  /**
   * Constructs password suggestions tips.
   *
   * @param {object} passwordSettings
   *   An object containing password related settings and translated tex  t to
   *   display.
   * @param {string} passwordSettings.hasWeaknesses
   *   The title that precedes tips.
   * @param {Array.<string>} tips
   *   Array containing the tips.
   *
   * @return {string}
   *   Markup for the password suggestions.
   */
  Drupal.theme.passwordSuggestions = ({ hasWeaknesses }, tips) =>
    `<div class="password-suggestions" aria-live="polite">${
      tips.length
        ? `${hasWeaknesses}<ul class="password-suggestions__tips"><li class="password-suggestions__tip">${tips.join(
            '</li><li class="password-suggestions__tip">',
          )}</li></ul>`
        : ''
    }</div>`;
})(Drupal);
