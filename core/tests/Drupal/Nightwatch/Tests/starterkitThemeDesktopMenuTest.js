const headerNavSelector = '#region-primary-navigation';
const linkSubMenuId = 'primary-nav-item-1';
const buttonSubMenuId = 'primary-nav-item-12';

module.exports = {
  '@tags': ['core', 'starterkit_theme'],
  before(browser) {
    browser.drupalInstall({
      setupFile:
        'core/tests/Drupal/TestSite/TestSiteStarterkitThemeInstallTestScript.php',
      installProfile: 'minimal',
    });
    browser.setWindowSize(1600, 800);
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Verify Starterkit Theme desktop menu click functionality': (browser) => {
    browser
      .drupalRelativeURL('/')
      .waitForElementVisible(headerNavSelector)
      .assert.not.visible(`#${linkSubMenuId}`)
      .assert.attributeEquals(
        `[aria-controls="${linkSubMenuId}"]`,
        'aria-expanded',
        'false',
      )
      .click(`[aria-controls="${linkSubMenuId}"]`)
      .assert.visible(`#${linkSubMenuId}`)
      .assert.attributeEquals(
        `[aria-controls="${linkSubMenuId}"]`,
        'aria-expanded',
        'true',
      )
      // Test interactions for route:<button> menu links.
      .assert.not.visible(`#${buttonSubMenuId}`)
      .assert.attributeEquals(
        `[aria-controls="${buttonSubMenuId}"]`,
        'aria-expanded',
        'false',
      )
      .click(`[aria-controls="${buttonSubMenuId}"]`)
      .assert.visible(`#${buttonSubMenuId}`)
      .assert.attributeEquals(
        `[aria-controls="${buttonSubMenuId}"]`,
        'aria-expanded',
        'true',
      );
  },
  'Verify Starterkit Theme desktop menu hover functionality': (browser) => {
    browser
      .drupalRelativeURL('/')
      .waitForElementVisible(headerNavSelector)
      .assert.visible(headerNavSelector)
      .assert.not.visible(`#${linkSubMenuId}`)
      .moveToElement(`[aria-controls="${linkSubMenuId}"]`, 1, 1)
      .assert.visible(`#${linkSubMenuId}`)
      .assert.attributeEquals(
        `[aria-controls="${linkSubMenuId}"]`,
        'aria-expanded',
        'true',
      )
      .assert.not.visible(`#${buttonSubMenuId}`)
      .moveToElement(`[aria-controls="${buttonSubMenuId}"]`, 1, 1)
      .assert.visible(`#${buttonSubMenuId}`)
      .assert.attributeEquals(
        `[aria-controls="${buttonSubMenuId}"]`,
        'aria-expanded',
        'true',
      );
  },
};
