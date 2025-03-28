module.exports = {
  '@tags': ['core', 'ajax', 'a11y'],
  before(browser) {
    browser.drupalInstall().drupalInstallModule('ajax_test', true);
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Test Progress Announce': (browser) => {
    browser
      .drupalRelativeURL('/ajax-test/dialog-form')
      .waitForElementVisible('body', 1000)
      .click('select[id="select-announcement"] option[value="two"]')
      .pause(1000);

    browser.expect
      .element('#drupal-live-announce')
      .text.to.equal('Processing...');
    browser.pause(5000);
    browser.expect
      .element('#drupal-live-announce')
      .text.not.to.equal('Processing...');
    browser.waitForElementNotPresent(
      '#select-announcement .ajax-progress',
      5000,
    );

    browser.end();
  },
};
