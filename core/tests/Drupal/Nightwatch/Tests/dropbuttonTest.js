const toggleButtonSelector = '.dropbutton-toggle button';
const secondaryActionSelector = '.secondary-action';

module.exports = {
  '@tags': ['core'],
  before(browser) {
    browser.drupalInstall({
      setupFile:
        'core/tests/Drupal/TestSite/TestSiteOliveroInstallTestScript.php',
      installProfile: 'minimal',
    });
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Verify dropbutton aria attributes': (browser) => {
    browser.drupalLoginAsAdmin(() => {
      browser
        .drupalRelativeURL('/admin/structure/types')
        .assert.textContains(toggleButtonSelector, 'Additional actions')
        // Verify aria-expanded changes with secondary action visibility.
        .assert.attributeEquals(toggleButtonSelector, 'aria-expanded', 'false')
        .assert.not.visible(secondaryActionSelector)
        .click(toggleButtonSelector)
        .assert.attributeEquals(toggleButtonSelector, 'aria-expanded', 'true')
        .assert.visible(secondaryActionSelector)
        // Verify aria-controls contains list of secondary action ids.
        .execute(
          (selector) => {
            const $ = jQuery;
            const ids = $(selector).toArray();
            return ids
              .map((el) => {
                return $(el).attr('id');
              })
              .join(',');
          },
          [secondaryActionSelector],
          (ids) => {
            browser.assert.attributeEquals(
              toggleButtonSelector,
              'aria-controls',
              ids.value,
            );
          },
        );
    });
  },
};
