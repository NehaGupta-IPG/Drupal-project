module.exports = {
  '@tags': ['core'],
  before(browser) {
    browser.drupalInstall().drupalInstallModule('ajax_test', true);
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Test Drupal.url.isLocal': (browser) => {
    browser
      // Any anonymous page that loads Drupal.behaviors-driven JS works here;
      // this AJAX test page is a reliable way to guarantee the "core/drupal"
      // and "core/drupalSettings" libraries are attached, since the bare
      // "nightwatch_testing" install profile's own pages attach neither.
      .drupalRelativeURL('/ajax-test/promise-form')
      .waitForElementVisible('body', 1000)
      .execute(
        function () {
          return Drupal.url.isLocal('/some/relative/path');
        },
        [],
        (result) => {
          browser.assert.strictEqual(
            result.value,
            true,
            'A relative URL is considered local.',
          );
        },
      )
      .execute(
        function () {
          return Drupal.url.isLocal(
            `${window.location.origin}/some/absolute/path`,
          );
        },
        [],
        (result) => {
          browser.assert.strictEqual(
            result.value,
            true,
            'An absolute URL with the same origin is considered local.',
          );
        },
      )
      .execute(
        function () {
          return Drupal.url.isLocal('http://example.com/');
        },
        [],
        (result) => {
          browser.assert.strictEqual(
            result.value,
            false,
            'An absolute URL with a different origin is not considered local.',
          );
        },
      )
      .execute(
        function () {
          return Drupal.url.isLocal(`//example.com${window.location.pathname}`);
        },
        [],
        (result) => {
          browser.assert.strictEqual(
            result.value,
            false,
            'A protocol-relative URL pointing to a different host is not considered local.',
          );
        },
      )
      .execute(
        function () {
          // A URL that embeds the site's own host as userinfo before an
          // attacker-controlled host must not be treated as local: the
          // actual origin is the host after the "@", not the one before it.
          return Drupal.url.isLocal(
            `${window.location.protocol}//${window.location.host}@evil.example.org/malicious`,
          );
        },
        [],
        (result) => {
          browser.assert.strictEqual(
            result.value,
            false,
            'A URL using the site host as userinfo for a different host is not considered local.',
          );
        },
      )
      .execute(
        function () {
          // Legitimate userinfo (credentials) preceding the real site host
          // must still be recognized as local.
          return Drupal.url.isLocal(
            `${window.location.protocol}//user:pass@${window.location.host}/legitimate`,
          );
        },
        [],
        (result) => {
          browser.assert.strictEqual(
            result.value,
            true,
            'A URL with credentials followed by the real site host is considered local.',
          );
        },
      )
      .execute(
        function () {
          return Drupal.url.isLocal(
            `https://${window.location.host}${drupalSettings.path.baseUrl}`,
          );
        },
        [],
        (result) => {
          browser.assert.strictEqual(
            result.value,
            true,
            'The HTTPS version of the site base URL is considered local even when the current page is served over HTTP.',
          );
        },
      )
      .execute(
        function () {
          // Malformed percent-encoded sequences must not throw; isLocal()
          // should fall back gracefully instead of erroring out.
          try {
            Drupal.url.isLocal('/%E0%A4%A');
            return true;
          } catch {
            return false;
          }
        },
        [],
        (result) => {
          browser.assert.strictEqual(
            result.value,
            true,
            'A malformed URI does not throw an exception.',
          );
        },
      )
      .drupalLogAndEnd({ onlyOnError: false });
  },
};
