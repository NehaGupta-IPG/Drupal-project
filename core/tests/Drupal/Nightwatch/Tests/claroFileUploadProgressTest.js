module.exports = {
  '@tags': ['core', 'claro'],
  before(browser) {
    browser
      .drupalInstall({
        setupFile:
          'core/tests/Drupal/TestSite/TestSiteClaroInstallTestScript.php',
        installProfile: 'minimal',
      })
      .drupalCreateUser({
        name: 'user',
        password: '123',
        permissions: ['administer site configuration'],
      })
      .drupalLogin({ name: 'user', password: '123' });
    browser.window.setSize(1600, 800);
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Confirm the progress bar works correctly when uploading a file': (browser) => {
    browser
    // TODO: Click the upload button and verify DOM elements
  },
};