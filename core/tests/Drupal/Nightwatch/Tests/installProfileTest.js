// The test fails for MongoDB, because the recipe's from the demo_umami profile
// break on MongoDB.
module.exports = {
  '@tags': ['core'],
  before(browser) {
    browser.drupalInstall({
      setupFile: 'core/tests/Drupal/TestSite/TestSiteInstallTestScript.php',
      installProfile: 'minimal',
    });
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Test umami profile': (browser) => {
    browser
      .drupalRelativeURL('/test-page')
      .waitForElementVisible('body', 1000)
      .drupalLogAndEnd({ onlyOnError: false });
  },
};
