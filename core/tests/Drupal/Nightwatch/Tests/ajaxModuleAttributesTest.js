module.exports = {
  '@tags': ['core', 'ajax'],
  before(browser) {
    browser.drupalInstall().drupalInstallModule('ajax_test', true);
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Module Attributes': (browser) => {
    browser
      .drupalRelativeURL('/ajax-test/module-attributes')
      .waitForElementVisible('#ajax_test_counter', 1000)
      .assert.textEquals(
        '#ajax_test_counter',
        '0',
        'Counter is in initial state',
      )
      .click('[data-drupal-selector="edit-increase-count-button"]')
      .waitForElementPresent('script[src*="ajax_test/js/module.js"]', 1000)
      .assert.attributeEquals(
        'script[src*="ajax_test/js/module.js"]',
        'type',
        'module',
        'Module has type="module"',
      )
      .assert.not.elementPresent(
        'script[src*="ajax_test/js/script.js"]',
        'Script with nomodule is not loaded',
      )
      .waitForElementVisible('#ajax_test_counter', 1000)
      .assert.textEquals('#ajax_test_counter', '1', 'Counter increased');
  },
};
