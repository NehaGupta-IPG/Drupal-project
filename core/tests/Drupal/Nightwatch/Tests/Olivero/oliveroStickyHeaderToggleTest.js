const { testPerTheme } = require('../../globals');

const buttonSelector = 'button.sticky-header-toggle';
const mainMenuSelector = '#block-olivero-main-menu';

module.exports = {
  '@tags': ['core', 'olivero'],
  before(browser) {
    browser.drupalInstall({
      setupFile:
        'core/tests/Drupal/TestSite/TestSiteOliveroInstallTestScript.php',
      installProfile: 'minimal',
    });
    browser.window.setSize(1400, 800);
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'On scroll, menu collapses to burger 🍔 menu': (browser) => {
    testPerTheme(browser, (browser, theme, title) => {
      browser
      .drupalRelativeURL('/node')
      .assert.not.visible(buttonSelector)
      .assert.attributeEquals(buttonSelector, 'aria-checked', 'false')
      .click('.block-system-powered-by-block .drupal-logo')
      .assert.visible(buttonSelector)
      .assert.not.visible('#site-header__inner')
      .assert.not.visible(mainMenuSelector)
      .click(buttonSelector)
      .assert.visible(mainMenuSelector)
      .assert.attributeEquals(buttonSelector, 'aria-checked', 'true')
    });
  },
};
