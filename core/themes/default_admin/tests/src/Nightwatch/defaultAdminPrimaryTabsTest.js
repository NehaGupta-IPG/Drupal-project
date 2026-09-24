const primaryTabsWrapper = '[data-drupal-nav-tabs]';
// nav-tabs.js:66 adds this class to every wrapper it initializes, so it marks
// the moment the measuring and reordering below have been applied.
const initializedTabs = `${primaryTabsWrapper}.is-horizontal-enabled`;
const primaryTabsTarget = `${primaryTabsWrapper} [data-drupal-nav-tabs-target]`;
const tabItem = `${primaryTabsTarget} > .tabs__tab`;
const firstTab = `${tabItem}:first-child`;
const secondTab = `${tabItem}:nth-child(2)`;
const movedTab = `${tabItem}.is-active:first-child`;
const activeTab = '.tabs__tab.is-active';
const inactiveTab = '.tabs__tab:not(.is-active)';
const tabLink = '.tabs__link';
const mobileToggle = `${activeTab} .tabs__trigger`;
const hamburgerIcon = `${mobileToggle} .hamburger-icon`;
const closeIcon = `${mobileToggle} .close-icon`;

module.exports = {
  '@tags': ['core', 'default_admin'],
  before(browser) {
    browser
      .drupalInstall({
        setupFile:
          'core/tests/Drupal/TestSite/TestSiteDefaultAdminInstallTestScript.php',
        installProfile: 'minimal',
      })
      .drupalCreateUser({
        name: 'user',
        password: '123',
        permissions: ['administer nodes'],
      })
      .drupalLogin({ name: 'user', password: '123' });
    browser.window.setSize(1600, 800);
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Verify desktop primary tab display': (browser) => {
    browser
      .drupalRelativeURL('/node/1')
      .waitForElementVisible(primaryTabsWrapper)
      .waitForElementVisible(initializedTabs)
      .assert.visible(activeTab)
      .assert.visible(inactiveTab)
      .assert.not.visible(mobileToggle)

      // nav-tabs.js:51 stores the combined width of the tab links, which
      // nav-tabs.js:56 compares against the width of the wrapper.
      .assert.attributeMatches(
        primaryTabsWrapper,
        'data-width',
        /^[1-9][0-9]*(\.[0-9]+)?$/,
      )

      // nav-tabs.js:57 keeps the wrapper horizontal while the tabs fit.
      .assert.hasClass(primaryTabsWrapper, 'is-horizontal')

      // nav-tabs.js:58 drops the aria-expanded state of the toggle while the
      // tabs are not collapsed.
      .assert.not.hasAttribute(mobileToggle, 'aria-expanded')

      // nav-tabs.js:70 records the rendered position of every primary tab.
      .assert.attributeEquals(firstTab, 'data-original-order', '0')

      // nav-tabs.js:24 returns without moving anything when the active tab is
      // the one rendered first, which is the "View" tab of a node page.
      .assert.hasClass(firstTab, 'is-active')

      // nav-tabs.js:15 only opens the tab list when the toggle is used.
      .assert.not.hasClass(primaryTabsTarget, 'is-open');
  },
  'Verify mobile tab display and click functionality': (browser) => {
    browser.window
      .setSize(699, 800)
      .drupalRelativeURL('/node/1')
      .waitForElementVisible(primaryTabsWrapper)
      .waitForElementVisible(initializedTabs)
      .assert.visible(activeTab)
      .assert.not.visible(inactiveTab)
      .assert.visible(mobileToggle)
      .assert.visible(hamburgerIcon)
      .assert.not.visible(closeIcon)

      // nav-tabs.js:45 only measures the tabs while they are horizontal, so a
      // page that is loaded below the breakpoint gets no width at all.
      .assert.not.hasAttribute(primaryTabsWrapper, 'data-width')
      .assert.not.hasClass(primaryTabsTarget, 'is-open')
      .assert.attributeEquals(mobileToggle, 'aria-expanded', 'false')
      .assert.attributeEquals(hamburgerIcon, 'aria-hidden', 'false')
      .assert.attributeEquals(closeIcon, 'aria-hidden', 'true')
      .click(mobileToggle)
      .waitForElementVisible(inactiveTab)

      // nav-tabs.js:15 toggles is-open on the tab list itself.
      .assert.hasClass(primaryTabsTarget, 'is-open')
      .assert.attributeEquals(mobileToggle, 'aria-expanded', 'true')
      .assert.not.visible(hamburgerIcon)
      .assert.visible(closeIcon)
      .assert.attributeEquals(hamburgerIcon, 'aria-hidden', 'true')
      .assert.attributeEquals(closeIcon, 'aria-hidden', 'false')
      .click(mobileToggle)
      .waitForElementNotVisible(inactiveTab)
      .assert.not.hasClass(primaryTabsTarget, 'is-open')
      .assert.attributeEquals(mobileToggle, 'aria-expanded', 'false')
      .assert.visible(hamburgerIcon)
      .assert.not.visible(closeIcon)
      .assert.attributeEquals(hamburgerIcon, 'aria-hidden', 'false')
      .assert.attributeEquals(closeIcon, 'aria-hidden', 'true');
  },
  'Verify collapsed tabs move the active tab to the front': (browser) => {
    browser.window
      .setSize(1600, 800)

      // The account page renders a "View" and an "Edit" primary tab, so on the
      // edit form the active tab is not the one that was rendered first.
      .drupalRelativeURL('/user')
      .waitForElementVisible(initializedTabs)

      // nav-tabs.js:24 returns early on the account page, because the active
      // "View" tab was rendered in position 0.
      .assert.attributeEquals(firstTab, 'data-original-order', '0')
      .assert.hasClass(firstTab, 'is-active')
      .click(`${inactiveTab} ${tabLink}`)
      .assert.urlMatches(/\/user\/\d+\/edit/)
      .waitForElementVisible(initializedTabs)

      // nav-tabs.js:24 also returns early while the tabs are horizontal, so
      // the active "Edit" tab stays in the position it was rendered in.
      .assert.attributeEquals(firstTab, 'data-original-order', '0')
      .assert.not.hasClass(firstTab, 'is-active')
      .assert.attributeEquals(secondTab, 'data-original-order', '1')
      .assert.hasClass(secondTab, 'is-active');

    browser.window.setSize(699, 800);
    browser
      .refresh()

      // nav-tabs.js:61 requests the collapsed order for a narrow viewport and
      // nav-tabs.js:39 inserts the active tab in front of the first one.
      .waitForElementVisible(movedTab)

      // nav-tabs.js:70 had recorded position 1 for the active "Edit" tab, so
      // the attribute proves that the list item itself was moved.
      .assert.attributeEquals(firstTab, 'data-original-order', '1')
      .assert.attributeEquals(secondTab, 'data-original-order', '0')
      .assert.not.hasClass(secondTab, 'is-active');
  },
};
