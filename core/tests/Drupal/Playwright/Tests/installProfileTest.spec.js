const { test, expect } = require('../drupal-fixtures');

test.use({
  drupalInstallOptions: {
    setupFile: 'core/tests/Drupal/TestSite/TestSiteInstallTestScript.php',
    installProfile: 'demo_umami',
  },
});

test.describe('Install profile', { tag: '@core' }, () => {
  test('Test umami profile', async ({ drupalPage: page }) => {
    await page.goto('/test-page');
    await expect(page.locator('body')).toBeVisible();
    await expect(page.locator('#block-umami-branding')).toBeAttached();
  });
});
