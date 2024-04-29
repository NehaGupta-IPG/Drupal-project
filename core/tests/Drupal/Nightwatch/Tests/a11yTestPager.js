module.exports = {
  '@tags': ['core'],
  before(browser) {
    browser
      .drupalInstall({
        installProfile: 'nightwatch_a11y_testing',
      })
      .drupalInstallModule('pager_test', true);
    browser.setWindowSize(1400, 800);
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'pager ellipsis is accessible': (browser) => {
    browser
      .drupalRelativeURL('/pager-test/ellipsis')
      .assert.visible('.pager__item--ellipsis')
      .axeInject()
      .axeRun('.pager', {
        rules: {
          'heading-order': { enabled: false },
        }
      });
  },
};
