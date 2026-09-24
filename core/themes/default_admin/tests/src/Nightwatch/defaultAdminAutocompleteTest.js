// cspell:ignore is-autocompleting

module.exports = {
  '@tags': ['core', 'default_admin'],

  before(browser) {
    browser
      .drupalInstall()
      .drupalInstallModule('form_test', true)
      .drupalLoginAsAdmin(() => {
        browser
          .drupalRelativeURL('/admin/appearance')
          .click('[title="Install Default Admin as default theme"]')
          .waitForElementVisible('.system-themes-list', 10000); // Confirm installation.
      });
  },
  after(browser) {
    browser.drupalUninstall();
  },

  'Test Default Admin autocomplete': (browser) => {
    browser
      .drupalCreateUser({
        name: 'user',
        password: '123',
        permissions: ['access autocomplete test'],
      })
      .drupalLogin({ name: 'user', password: '123' })
      .drupalRelativeURL('/form-test/autocomplete')
      .waitForElementVisible('body', 1000);

    // Tests that entering a character from the
    // data-autocomplete-first-character-denylist doesn't start the
    // autocomplete process.
    browser
      .setValue('[name="autocomplete_4"]', '/')
      .pause(1000)
      .waitForElementNotPresent('.is-autocompleting');

    // Tests both the autocomplete-message nor the autocomplete dropdown are
    // present when nothing has been entered in autocomplete-3.

    // eslint-disable-next-line no-unused-expressions
    browser.expect.element(
      '.js-form-item-autocomplete-3 [data-drupal-selector="autocomplete-message"]',
    ).to.not.visible;
    // eslint-disable-next-line no-unused-expressions
    browser.expect.element('#ui-id-3.ui-autocomplete').to.not.visible;

    // Tests that upon entering some text in autocomplete-3, first the
    // autocomplete-message appears and then the autocomplete dropdown with a
    // result. At that point the autocomplete-message should be invisible again.

    // The is-autocompleting class added by autocomplete.js:23 is removed again
    // by autocomplete.js:13 as soon as the response arrives, so it only exists
    // for the duration of one request - too short a window to sample by
    // polling. Record every class change of the input instead, and inspect the
    // recording once the results are in.
    browser.waitForElementVisible('[name="autocomplete_3"]');
    browser.execute(() => {
      const input = document.querySelector('[name="autocomplete_3"]');
      window.defaultAdminAutocompleteSeen = false;
      const observer = new MutationObserver((mutations) => {
        if (input.classList.contains('is-autocompleting')) {
          window.defaultAdminAutocompleteSeen = true;
        }
        mutations.forEach((mutation) => {
          const previous = mutation.oldValue || '';
          if (previous.includes('is-autocompleting')) {
            window.defaultAdminAutocompleteSeen = true;
          }
        });
      });
      observer.observe(input, {
        attributeFilter: ['class'],
        attributeOldValue: true,
        attributes: true,
      });
    });

    // eslint-disable-next-line no-unused-expressions
    browser
      .setValue('[name="autocomplete_3"]', '123')
      .waitForElementVisible(
        '.js-form-item-autocomplete-3 [data-drupal-selector="autocomplete-message"]',
      )
      .waitForElementVisible('#ui-id-3.ui-autocomplete')
      .expect.element(
        '.js-form-item-autocomplete-3 [data-drupal-selector="autocomplete-message"]',
      ).to.not.visible;

    // autocomplete.js:23 marked the input while the search was in flight.
    browser.execute(
      () => window.defaultAdminAutocompleteSeen,
      (result) => {
        browser.assert.strictEqual(
          result.value,
          true,
          'The input was marked as autocompleting during the search.',
        );
      },
    );

    // autocomplete.js:13 removed the mark again when the response arrived.
    browser.waitForElementNotPresent(
      '[name="autocomplete_3"].is-autocompleting',
    );

    browser.drupalLogAndEnd({ onlyOnError: false });
  },
};
