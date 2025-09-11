/* eslint-disable import/no-extraneous-dependencies */
const argv = require('minimist')(process.argv.slice(2));

const machineName = 'test_view';

module.exports = {
  '@tags': ['core', 'a11y', 'a11y:admin', 'views'],

  before(browser) {
    browser
      .drupalInstall({
        installProfile: 'nightwatch_a11y_testing',
      })
      .drupalInstallModule('views_ui', true);

    // If the default theme is set to something other than Olivero, install it.
    if (
      argv.defaultTheme &&
      argv.defaultTheme !== browser.globals.defaultTheme
    ) {
      browser.drupalEnableTheme(argv.defaultTheme);
    }
    // If an admin theme other than Claro is being used for testing, install it.
    if (argv.adminTheme && argv.adminTheme !== browser.globals.adminTheme) {
      browser.drupalEnableTheme(argv.adminTheme, true);
    }

    // Create test view.
    browser.drupalLoginAsAdmin(() => {
      browser
        .drupalRelativeURL('/admin/structure/views/add')
        // View name.
        .updateValue('[data-drupal-selector="edit-label"]', machineName)
        // View page.
        .check('[data-drupal-selector="edit-page-create"]')
        .updateValue('[data-drupal-selector="edit-page-path"]', machineName)
        .click('input[value="Save and edit"]');
    });
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Accessibility - Numeric filter': (browser) => {
    browser.drupalLoginAsAdmin(() => {
      browser
        // Add an NID filter to the test view.
        .drupalRelativeURL(
          `/admin/structure/views/nojs/add-handler/${machineName}/page_1/filter`,
        )
        // cspell:ignore datanid
        .check('[data-drupal-selector="edit-name-node-field-datanid"]')
        .submitForm('input[value="Add and configure filter criteria"]')
        // Expose the filter and operator.
        .click('input[value="Expose filter"]')
        .check('[data-drupal-selector="edit-options-expose-use-operator"]')
        .click('input[value="Apply"]')
        // Confirm that element is visible on page to be tested
        // by Axe.
        .waitForElementVisible('[data-drupal-selector="edit-nid-value"]')
        // Test view preview in admin.
        .axeInject()
        .axeRun('body', {})
        .click('input[value="Save"]');

      // Test actual view page.
      browser
        .drupalRelativeURL(`/${machineName}`)
        // Confirm that element is visible on page to be tested
        // by Axe.
        .waitForElementVisible('[data-drupal-selector="edit-nid-value"]')
        .axeInject()
        .axeRun('body', {});
    });
  },
};
