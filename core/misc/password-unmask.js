/**
 * @file
 * Password Unmask.
 */
(function (Drupal, once) {
  const hidePass = Drupal.t('Hide password');
  const showPass = Drupal.t('Show password');
  const unmaskClickHandler = (element, trigger) => {
    const isPassword = element.getAttribute('type') === 'password';
    element.setAttribute('type', isPassword ? 'text' : 'password');
    trigger.textContent =
      element.getAttribute('type') === 'password' ? showPass : hidePass;
    trigger.setAttribute('aria-checked', isPassword ? 'true' : 'false');
    trigger.classList.toggle('action-link--icon-show', !isPassword);
    trigger.classList.toggle('action-link--icon-hide', isPassword);
    const ariaDescription = isPassword
      ? Drupal.t('Password is hidden')
      : Drupal.t('Password is visible');
    element.setAttribute('aria-description', ariaDescription);
  };
  const unmaskButton = function unmaskButton(element) {
    const wrapperButton = Drupal.theme.buttonWrapper(element);
    element.insertAdjacentElement('afterend', wrapperButton);
    wrapperButton.addEventListener('click', () => {
      return unmaskClickHandler(element, wrapperButton);
    });
  };

  /**
   * Theme function for a button-wrapper.
   *
   * @param {HTMLElement} element
   *   The input element.
   *
   * @return {HTMLElement}
   *   A DOM Node.
   */
  Drupal.theme.buttonWrapper = (element) => {
    const wrapperButton = document.createElement('button');
    wrapperButton.setAttribute('type', 'button');
    wrapperButton.setAttribute(
      'class',
      'link password-wrapper action-link action-link--extrasmall action-link--icon-show toggle-password',
    );
    wrapperButton.setAttribute(
      'aria-checked',
      element.getAttribute('type') === 'password' ? 'true' : 'false',
    );
    wrapperButton.textContent = showPass;
    wrapperButton.setAttribute('aria-label', Drupal.t('Password invisible'));
    wrapperButton.setAttribute('role', 'switch');
    return wrapperButton;
  };

  Drupal.behaviors.passwordUnmask = {
    attach: function attach(context) {
      once(
        'password-unmask',
        'input[type=password][data-drupal-password-unmask]',
        context,
      ).forEach((password) => {
        unmaskButton(password);
      });
    },
  };
})(Drupal, once);
