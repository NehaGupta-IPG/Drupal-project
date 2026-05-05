module.exports = {
  '@tags': ['core'],
  before(browser) {
    browser.drupalInstall().drupalInstallModule('js_sizzle_deprecation_test');
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Sizzle-only selectors trigger a deprecation warning': (browser) => {
    browser
      .drupalRelativeURL('/js_sizzle_deprecation_test')
      .waitForElementVisible('body', 1000)
      .assert.textContains('h1', 'JsSizzleDeprecationTest')
      // jQuery-only selector must be reported as deprecated.
      .assert.deprecationErrorExists(
        'Using jQuery-only selector ":input" is deprecated in drupal:11.4.0 and is removed from drupal:12.0.0.',
      )
      // A valid CSS selector must not be reported as deprecated. This guards
      // against regressions where deprecateSizzleSelector would warn for any
      // thrown error rather than only SyntaxError.
      .execute(
        function () {
          const entries = JSON.parse(
            window.sessionStorage.getItem('js_testing_log_test.warnings'),
          );
          return (entries || []).some((message) =>
            message.includes('.valid-selector'),
          );
        },
        [],
        (result) => {
          browser.assert.strictEqual(
            result.value,
            false,
            'A valid CSS selector must not trigger a Sizzle deprecation warning.',
          );
        },
      )
      .drupalLogAndEnd({ onlyOnError: false });
  },
};
