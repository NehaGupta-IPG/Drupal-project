module.exports = {
  '@tags': ['core', 'ajax'],
  before(browser) {
    browser.drupalInstall({
      setupFile:
        'core/tests/Drupal/TestSite/TestSiteViewAjaxInstallTestScript.php',
      installProfile: 'minimal',
    });
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Test View AJAX GET parameters': (browser) => {
    browser.drupalLoginAsAdmin(() => {
      browser
        .drupalRelativeURL('/admin/content?title=&type=All&status=All')
        .waitForElementVisible('body', 1000)
        .captureNetworkRequests((requestParams) => {
          if (
            requestParams.request.headers['X-Requested-With'] ===
            'XMLHttpRequest'
          ) {
            const url = new URL(requestParams.request.url);
            browser.assert.strictEqual(
              url.searchParams.getAll('title').length,
              1,
              'Duplicate title parameter',
            );
            browser.assert.strictEqual(
              url.searchParams.getAll('type').length,
              1,
              'Duplicate type parameter',
            );
            browser.assert.strictEqual(
              url.searchParams.getAll('status').length,
              1,
              'Duplicate status parameter',
            );
          }
        })
        .click('#edit-submit-content');
    });
  },
};
