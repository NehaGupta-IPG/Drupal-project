const { test, expect } = require('../drupal-fixtures');

test.use({
  drupalInstallOptions: {
    setupFile:
      'core/tests/Drupal/TestSite/TestSiteMultilingualInstallTestScript.php',
    langcode: 'fr',
  },
});

test.describe('Langcode', { tag: '@core' }, () => {
  test('Test page with langcode', async ({ drupalPage: page }) => {
    await page.goto('/test-page');
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
  });
});
