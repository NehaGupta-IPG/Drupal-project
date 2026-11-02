module.exports = {
  '@tags': ['core', 'a11y'],
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
          // Disabling the heading-order rule because the pagination
          // by default has h4 which breaks the heading order rule.
          'heading-order': { enabled: false },
        },
      });
  },
};
