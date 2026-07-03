/**
 * @file
 * User behaviors.
 */

(($, Drupal) => {
  /**
   * An object containing CSS classes used for password widget.
   *
   * @type {object}
   * @prop {string} passwordParent - A CSS class for the parent element.
   * @prop {string} passwordsMatch - A CSS class indicating password match.
   * @prop {string} passwordsNotMatch - A CSS class indicating passwords
   *   doesn't match.
   * @prop {string} passwordWeak - A CSS class indicating weak password
   *   strength.
   * @prop {string} passwordFair - A CSS class indicating fair password
   *   strength.
   * @prop {string} passwordGood - A CSS class indicating good password
   *   strength.
   * @prop {string} passwordStrong - A CSS class indicating strong password
   *   strength.
   * @prop {string} widgetInitial - Initial CSS class that should be removed
   *   on a state change.
   * @prop {string} passwordEmpty - A CSS class indicating password has not
   *   been filled.
   * @prop {string} passwordFilled - A CSS class indicating password has
   *   been filled.
   * @prop {string} confirmEmpty - A CSS class indicating password
   *   confirmation has not been filled.
   * @prop {string} confirmFilled - A CSS class indicating password
   *   confirmation has been filled.
   */
  Drupal.user = {
    password: {
      css: {
        passwordParent: 'password-parent',
        passwordsMatch: 'ok',
        passwordsNotMatch: 'error',
        passwordWeak: 'is-weak',
        passwordFair: 'is-fair',
        passwordGood: 'is-good',
        passwordStrong: 'is-strong',
        widgetInitial: '',
        passwordEmpty: '',
        passwordFilled: '',
        confirmEmpty: '',
        confirmFilled: '',
      },
    },
  };

  /**
   * Attach handlers to evaluate the strength of any password fields and to
   * check that its confirmation is correct.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches password strength indicator and other relevant validation to
   *   password fields.
   */
  Drupal.behaviors.password = {
    attach(context, settings) {
      const cssClasses = Drupal.user.password.css;
      once('password', 'input.js-password-field', context).forEach((value) => {
        let tmp = '';
        const $mainInput = $(value);
        const $mainInputParent = $mainInput
          .parent()
          .addClass(cssClasses.passwordParent);
        const $passwordWidget = $mainInput.closest(
          '.js-form-type-password-confirm',
        );
        const $confirmInput = $passwordWidget.find('input.js-password-confirm');
        const $passwordConfirmMessage = $(
          Drupal.theme('passwordConfirmMessage', settings.password),
        );

        const $passwordMatchStatus = $passwordConfirmMessage
          .find('[data-drupal-selector="password-match-status-text"]')
          .first();

        $confirmInput
          .parent()
          .addClass('confirm-parent')
          .append($passwordConfirmMessage);

        // List of classes to be removed from the text wrapper on a state
        // change.
        const confirmTextWrapperClassesToRemove = [
          cssClasses.passwordsMatch || '',
          cssClasses.passwordsNotMatch || '',
        ]
          .join(' ')
          .trim();

        // List of classes to be removed from the widget on a state change.
        const widgetClassesToRemove = [
          cssClasses.widgetInitial || '',
          cssClasses.passwordEmpty || '',
          cssClasses.passwordFilled || '',
          cssClasses.confirmEmpty || '',
          cssClasses.confirmFilled || '',
        ]
          .join(' ')
          .trim();

        const password = {};

        // If the password strength indicator is enabled, add its markup.
        if (settings.password.showStrengthIndicator) {
          const suggestionId = `${$mainInput[0].id}-suggestions`;
          password.$suggestions = $(
            Drupal.theme('passwordSuggestions', settings.password, []),
          );
          password.$suggestions.attr('id', suggestionId);

          $mainInputParent.append(password.$suggestions);
          $mainInput.attr('aria-details', suggestionId);
        }

        /**
         * Adds classes to the widget indicating if the elements are filled.
         */
        const addWidgetClasses = () => {
          $passwordWidget
            .addClass(
              $mainInput[0].value
                ? cssClasses.passwordFilled
                : cssClasses.passwordEmpty,
            )
            .addClass(
              $confirmInput[0].value
                ? cssClasses.confirmFilled
                : cssClasses.confirmEmpty,
            );
        };

        /**
         * Check that password and confirmation inputs match.
         *
         * @param {string} confirmInputVal
         *   The value of the confirm input.
         */
        const passwordCheckMatch = (confirmInputVal) => {
          const passwordsAreMatching = $mainInput[0].value === confirmInputVal;
          const confirmClass = passwordsAreMatching
            ? cssClasses.passwordsMatch
            : cssClasses.passwordsNotMatch;
          const confirmMessage = passwordsAreMatching
            ? settings.password.confirmSuccess
            : settings.password.confirmFailure;

          // Update the success message and set the class if needed.
          if (
            !$passwordMatchStatus.hasClass(confirmClass) ||
            !$passwordMatchStatus.html() === confirmMessage
          ) {
            if (confirmTextWrapperClassesToRemove) {
              $passwordMatchStatus.removeClass(
                confirmTextWrapperClassesToRemove,
              );
            }
            $passwordMatchStatus.html(confirmMessage).addClass(confirmClass);
          }
        };

        /**
         * Checks the password strength.
         */
        const passwordCheck = () => {
          if (settings.password.showStrengthIndicator) {
            // Evaluate the password strength.
            const result = Drupal.evaluatePasswordStrength(
              $mainInput[0].value,
              settings.password,
            );
            const $currentPasswordSuggestions = $(
              Drupal.theme(
                'passwordSuggestions',
                settings.password,
                result.messageTips,
              ),
            );
            if (result.messageTips.length === 0) {
              Drupal.announce(Drupal.t('Password requirements met'));
            }
            else if (result.messageTips[0] !== tmp) {
              Drupal.announce(result.messageTips[0]);
              tmp = result.messageTips[0];
            }

            // Update the suggestions for how to improve the password if needed.
            if (
              password.$suggestions.contents() !==
              $currentPasswordSuggestions.contents()
            ) {
              password.$suggestions
                .empty()
                .append($currentPasswordSuggestions.contents());
            }
          }

          // Check the value in the confirm input and show results.
          if ($confirmInput[0].value) {
            passwordCheckMatch($confirmInput[0].value);
            $passwordConfirmMessage[0].style.visibility = 'visible';
          } else {
            $passwordConfirmMessage[0].style.visibility = 'hidden';
          }

          if (widgetClassesToRemove) {
            $passwordWidget.removeClass(widgetClassesToRemove);
            addWidgetClasses();
          }
        };

        if (widgetClassesToRemove) {
          addWidgetClasses();
        }

        // Monitor input events.
        $mainInput.on('input', passwordCheck);
        $confirmInput.on('input', passwordCheck);
        passwordCheck();
      });
    },
  };

  Drupal.strengthTests = {
    addLowerCase: {
      test: (password) => /[a-z]/.test(password),
      message: (settings) => settings.addLowerCase,
    },
    addUpperCase: {
      test: (password) => /[A-Z]/.test(password),
      message: (settings) => settings.addUpperCase,
    },
    addNumbers: {
      test: (password) => /[0-9]/.test(password),
      message: (settings) => settings.addNumbers,
    },
    addPunctuation: {
      test: (password) => /[^a-zA-Z0-9]/.test(password),
      message: (settings) => settings.addPunctuation,
    },
  };

  /**
   * Evaluate the strength of a user's password.
   *
   * Returns the estimated strength and the relevant output message.
   *
   * @param {string} password
   *   The password to evaluate.
   * @param {object} passwordSettings
   *   A password settings object containing the text to display and the CSS
   *   classes for each strength level.
   *
   * @return {object}
   *   An object containing strength, message, indicatorText and indicatorClass.
   */
  Drupal.evaluatePasswordStrength = (password, passwordSettings) => {
    password = password.trim();
    let weaknesses = 0;
    const messageTips = [];

    // If there is a username edit box on the page, compare password to that,
    // otherwise use value from the database.
    const $usernameBox = $('input.username');
    const username =
      $usernameBox.length > 0
        ? $usernameBox[0].value
        : passwordSettings.username;

    // Handle length different.
    // TODO Make length configurable.
    if (password.length < 12) {
      messageTips.push(passwordSettings.tooShort);
      weaknesses += 1;
    }

    // Count weaknesses.
    Object.values(Drupal.strengthTests).forEach((test) => {
      if (!test.test(password)) {
        messageTips.push(test.message(passwordSettings));
        weaknesses += 1;
      }
    });

    let strength = (weaknesses / Drupal.strengthTests.length + 1) * 100;

    // Check if password is the same as the username.
    if (password !== '' && password.toLowerCase() === username.toLowerCase()) {
      messageTips.push(passwordSettings.sameAsUsername);
      // Passwords the same as username are always very weak.
      strength = 5;
    }

    return {
      strength,
      messageTips,
    };
  };
})(jQuery, Drupal);
