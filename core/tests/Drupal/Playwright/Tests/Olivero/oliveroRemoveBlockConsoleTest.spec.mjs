import { test, expect, drupalLoginAsAdmin } from '../../drupal-fixtures';

test.use({
  drupalInstallOptions: {
    setupFile:
      'core/themes/olivero/tests/TestSite/TestSiteOliveroInstallTestScript.php',
    installProfile: 'minimal',
  },
});

test.describe(
  'Olivero remove block console',
  { tag: ['@core', '@olivero'] },
  () => {
    test('Verify no console errors', async ({ drupalPage: page }) => {
      await drupalLoginAsAdmin(page);
      await page.goto('./admin/structure/block');

      // Disable main menu block.
      await page
        .locator(
          '[data-drupal-selector="edit-blocks-olivero-main-menu-operations"] .dropbutton-toggle button',
        )
        .click();
      await page.locator('[href*="olivero_main_menu/disable"]').click();

      // Disable user account menu block.
      await page
        .locator(
          '[data-drupal-selector="edit-blocks-olivero-account-menu-operations"] .dropbutton-toggle button',
        )
        .click();
      await page.locator('[href*="olivero_account_menu/disable"]').click();

      // Verify no console errors on the front page.
      await page.goto('./');
      await page.locator('body').waitFor({ state: 'visible' });
      const noErrors = await page.evaluate(() => Drupal.errorLog.length === 0);
      expect(noErrors).toBe(true);
    });
  },
);
