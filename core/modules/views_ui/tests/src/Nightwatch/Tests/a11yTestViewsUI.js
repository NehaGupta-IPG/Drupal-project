module.exports = {
  '@tags': ['core', 'a11y', 'a11y:admin', 'views_ui'],

  before(browser) {
    browser
      .drupalInstall({
        installProfile: 'nightwatch_a11y_testing',
      })
      .drupalInstallModule('views_ui', true);
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Accessibility - Exposed, grouped filters configuration form': (browser) => {
    browser.drupalLoginAsAdmin(() => {
      browser
        // Add an NID filter to the built-in Content view.
        .drupalRelativeURL(
          '/admin/structure/views/nojs/add-handler/content/page_1/filter',
        )
        // cspell:ignore datanid
        .check('#edit-name-node-field-datanid')
        .submitForm('input[value="Add and configure filter criteria"]')
        // Expose grouped filters form.
        .click('input[value="Expose filter"]')
        .click('input[value="Grouped filters"]')
        // Confirm that possibly-hidden elements are visible to be tested
        // by Axe.
        .waitForElementVisible(
          '#edit-options-group-info-group-items-1-value-value',
        )
        .waitForElementVisible(
          '#edit-options-group-info-group-items-1-value-min',
        )
        .waitForElementVisible(
          '#edit-options-group-info-group-items-1-value-max',
        )
        // The remove checkbox may be hidden in the standard UI but should be
        // visible at the `nojs` URL.
        .waitForElementVisible('#views-removed-1')
        .axeInject()
        .axeRun('body', {});
    });
  },
};
